<?php declare(strict_types=1);

namespace DocteurKlein\ORM;

final class Products
{
    private $uow;
    private $connection;

    public function __construct(\pq\Connection $connection, $uow)
    {
        $this->connection = $connection;
        $this->uow = $uow;
    }

    public function find($id): Product
    {
        $resultSet = $this->getLazyResult($id);
        //$resultSet = $this->getEagerNestedResult($id);

        return Product::fromResultSet($resultSet);
    }

    public function findAll(): iterable
    {
        $result = $this->connection->prepare('fetch_products', <<<SQL
            select product.*
            from product
SQL
        )->exec([]);

        while ($row = $result->fetchRow(\pq\Result::FETCH_ASSOC)) {
            $row['attributes'] = $this->lazyLoadAttributes($row['product_id']);
            yield Product::fromResultSet($row);
        }
    }

    public function findAll2()
    {
        $result = $this->connection->prepare('fetch_products', <<<SQL
            select product.*, jsonb_agg(attribute.*) attributes
            from product
            left join product_attribute using (product_id)
            left join (select attribute.*, row_to_json(f.*) as family from attribute inner join family f using(family_id)) attribute using (attribute_id)
            group by product.product_id
SQL
        )->exec([]);


        while ($row = $result->fetchRow(\pq\Result::FETCH_ASSOC)) {
            $row['attributes'] = new \ArrayObject(array_map([Attribute::class, 'fromResultSet'], $row['attributes']));
            yield Product::fromResultSet($row);
        }
    }

    private function getLazyResult($id)
    {
        $result = $this->connection->prepare('fetch_products', <<<SQL
            select product.*
            from product
            where product.product_id = $1
SQL
        )->exec([$id]);
        $resultSet = $result->fetchRow(\pq\Result::FETCH_ASSOC);
        $resultSet['attributes'] = $this->lazyLoadAttributes($resultSet['product_id']);

        return $resultSet;
    }

    private function lazyLoadAttributes($productId)
    {
        return new CachedGenerator((function($productId) {
            $result = $this->connection->prepare('fetch_product_attributes', <<<SQL
                select attribute.*, row_to_json(f.*) as family
                from attribute
                inner join family f using(family_id)
                inner join product_attribute using(attribute_id)
                where product_id = $1
SQL
            )->exec([$productId]);

            while ($row = $result->fetchRow(\pq\Result::FETCH_ASSOC)) {
                yield Attribute::fromResultSet($row);
            }
        })($productId));
    }

    private function getEagerNestedResult($id)
    {
        $result = $this->connection->prepare('fetch_products', <<<SQL
            select product.*, jsonb_agg(attribute.*) attributes
            from product
            left join product_attribute using (product_id)
            left join (select attribute.*, row_to_json(f.*) as family from attribute inner join family f using(family_id)) attribute using (attribute_id)
            where product.product_id = $1
            group by product.product_id
SQL
        )->exec([$id]);
        $resultSet = $result->fetchRow(\pq\Result::FETCH_ASSOC);
        $resultSet['attributes'] = new \ArrayObject(array_map([Attribute::class, 'fromResultSet'], $resultSet['attributes']));

        return $resultSet;
    }

    public function add(Product $product): void
    {
        $this->uow->addStream($product->popEvents());
    }
}

final class GeneratorIterator implements \IteratorAggregate
{
    private $factory;
    private $args;

    public function __construct(callable $factory, array $args = [])
    {
        $this->factory = $factory;
        $this->args = $args;
    }

    public function getIterator()
    {
        return call_user_func_array($this->factory, $this->args);
    }
}

final class CachedGenerator implements \Iterator, \ArrayAccess
{
    private $generator;
    private $cache = [];
    private $consumed = false;

    public function __construct(\Generator $generator)
    {
        $this->generator = $generator;
    }

    public function offsetGet($offset)
    {
        return $this->cache[$offset];
    }

    public function offsetSet($offset, $value)
    {
        return $this->cache[$offset] = $value;
    }

    public function offsetExists($offset)
    {
        return isset($this->cache[$offset]);
    }

    public function offsetUnset($offset)
    {
        unset($this->cache[$offset]);
    }

    public function rewind()
    {
        try {
            $this->generator->rewind();
        }
        catch(\Exception $e) {
        }
        reset($this->cache);
    }

    public function current()
    {
        if ($this->consumed) {
            return current($this->cache);
        }
        return $this->cache[$this->generator->key()] = $this->generator->current();
    }

    public function key()
    {
        if ($this->consumed) {
            return key($this->cache);
        }
        return $this->generator->key();
    }

    public function next()
    {
        if ($this->consumed) {
            return next($this->cache);
        }
        $this->generator->next();
    }

    public function valid()
    {
        if ($this->consumed) {
            return !is_null(key($this->cache));
        }

        $valid = $this->generator->valid();
        if (!$valid) {
            $this->consumed = true;
        }

        return $valid;
    }
}

final class WriteProjection
{
    private $connection;
    private $streams = [];

    public function __construct(\pq\Connection $connection)
    {
        $this->connection = $connection;
    }

    public function addStream(iterable $events): void
    {
        $this->streams[] = $events;
    }

    public function commit(): void
    {
        foreach($this->streams as $stream) {
            if (!$stream->valid()) {
                continue;
            }
            $transaction = $this->connection->startTransaction();
            $transaction->connection->exec('SET CONSTRAINTS attribute_family_id_fkey DEFERRED');
            $transaction->connection->exec('SET CONSTRAINTS product_attribute_attribute_id_fkey DEFERRED');
            foreach ($stream as $event) {
                $eventName = strtolower((new \ReflectionObject($event))->getShortName());
                switch (true) {
                    case $event instanceof ProductCreated:
                        $transaction->connection->prepare($eventName, 'insert into product values($1)')->exec([
                            $event->id,
                        ]);
                        $transaction->connection->notify($eventName, $event->id);
                        break;
                    case $event instanceof FamilyCreated:
                        $transaction->connection->prepare($eventName, 'insert into family values($1, $2)')->exec([
                            $event->id, $event->name
                        ]);
                        $transaction->connection->notify($eventName, $event->id);
                        break;
                    case $event instanceof AttributeCreated:
                        $transaction->connection->prepare($eventName, 'insert into attribute values($1, $2)')->exec([
                            $event->id, $event->family->getId()
                        ]);
                        $transaction->connection->notify($eventName, $event->id);
                        break;
                    case $event instanceof AttributeAddedToProduct:
                        $transaction->connection->prepare($eventName, 'insert into product_attribute values($1, $2)')->exec([
                            $event->product->getId(), $event->attribute->getId()
                        ]);
                        $transaction->connection->notify($eventName, json_encode([
                            'product_id' => $event->product->getId(),
                            'attribute_id' => $event->attribute->getId(),
                        ]));
                        break;
                    case $event instanceof FamilyRenamed:
                        $transaction->connection->prepare($eventName, 'update family set name = $1 where family_id = $2')->exec([
                            $event->name, $event->id
                        ]);
                        $transaction->connection->notify($eventName, json_encode([
                            'family_id' => $event->id,
                            'name' => $event->name,
                        ]));
                        break;
                    case $event instanceof EntityChanged:
                        //$transaction->connection->prepare($eventName, sprintf('upadte %s set %s where id = :id', '', ''))->exec();
                        break;
                    default:
                        $transaction->connection->notify($eventName, null);
                        break;
                }
            }
            $transaction->commit();
        }
        $this->streams = [];
    }
}
