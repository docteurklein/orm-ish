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
        //$resultSet = $this->getLazyResult($id);
        $resultSet = $this->getEagerNestedResult($id);

        return Product::fromResultSet($resultSet);
    }

    public function findAll(): iterable
    {
        $result = $this->connection->prepare('fetch_products', <<<SQL
            select product.*
            from product
limit 1
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
        return new MemoizedGenerator((function($productId) {
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
        $resultSet['attributes'] = array_map([Attribute::class, 'fromResultSet'], $resultSet['attributes']);

        return $resultSet;
    }

    public function add(Product $product): void
    {
        $this->uow->addStream($product->popEvents());
    }
}

final class MemoizedGenerator implements \Iterator, \ArrayAccess
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
        $this->cache = iterator_to_array($this->generator);
        reset($this->cache);
        return $this->cache[$offset];
    }

    public function offsetSet($offset, $value)
    {
        $this->cache = iterator_to_array($this->generator);
        reset($this->cache);
        return $this->cache[$offset] = $value;
    }

    public function offsetExists($offset)
    {
        $this->cache = iterator_to_array($this->generator);
        reset($this->cache);
        return isset($this->cache[$offset]);
    }

    public function offsetUnset($offset)
    {
        $this->cache = iterator_to_array($this->generator);
        reset($this->cache);
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

final class PQWriteProjection
{
    private $connection;
    private $statements;
    private $streams = [];

    public function __construct(\pq\Connection $connection, array $statements)
    {
        $this->connection = $connection;
        $this->statements = $statements;
    }

    public function addStream(iterable $events): void
    {
        if (!$events->valid()) {
            return;
        }
        $this->streams[] = $events;
    }

    public function commit(): void
    {
        if (empty($this->streams)) {
            return;
        }
        $transaction = $this->connection->startTransaction();
        $transaction->connection->exec('SET CONSTRAINTS attribute_family_id_fkey DEFERRED');
        $transaction->connection->exec('SET CONSTRAINTS product_attribute_attribute_id_fkey DEFERRED');
        $prepared = [];
        foreach($this->streams as $stream) {
            foreach ($stream as $event) {
                $eventClass = get_class($event);
                if (isset($this->statements[$eventClass])) {
                    [$factory, $exec] = $this->statements[$eventClass];
                    $prepared[$eventClass] = $prepared[$eventClass] ?? $factory($transaction->connection);
                    $exec($prepared[$eventClass], $event);
                }
            }
        }
        $transaction->commit();
        $this->streams = [];
    }
}
