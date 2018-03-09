<?php declare(strict_types=1);

namespace DocteurKlein\ORM;

set_error_handler(function($no, $str, $file, $line) {
    throw new \ErrorException($str, $no, 1, $file, $line);
});

interface HasEvents
{
    function popEvents(): iterable;
}

trait PopEvents
{
    private $events = [];

    public function popEvents(): iterable
    {
        yield from $this->events;
        $this->events = [];

        foreach (get_object_vars($this) as $prop => $field) {
            if ($field instanceof HasEvents) {
                yield from $field->popEvents();
            }
            if (is_iterable($field)) {
                foreach ($field as $entry) {
                    if ($entry instanceof HasEvents) {
                        yield from $entry->popEvents();
                    }
                }
            }
        }
    }
}

final class ProductCreated
{
    public $id;
    public $attributes;

    public function __construct($id, $attributes)
    {
        $this->id = $id;
        $this->attributes = $attributes;
    }
}

final class AttributeCreated
{
    public $id;
    public $family;

    public function __construct($id, $family)
    {
        $this->id = $id;
        $this->family = $family;
    }
}

final class AttributeAddedToProduct
{
    public $product;
    public $attribute;

    public function __construct($product, $attribute)
    {
        $this->product = $product;
        $this->attribute = $attribute;
    }
}

final class FamilyCreated
{
    public $id;
    public $name;

    public function __construct($id, $name)
    {
        $this->id = $id;
        $this->name = $name;
    }
}

final class FamilyRenamed
{
    public $id;
    public $name;

    public function __construct($id, $name)
    {
        $this->id = $id;
        $this->name = $name;
    }
}

final class EntityChanged
{
    public $class;
    public $id;
    public $what;

    public function __construct(string $class, $id, array $what)
    {
        $this->class = $class;
        $this->id = $id;
        $this->what = $what;
    }
}

final class Product implements HasEvents
{
    use PopEvents;

    private $id;
    private $attributes;

    public static function fromResultSet(array $resultSet): self
    {
        $self = new self($resultSet['attributes']);
        $self->id = $resultSet['product_id'];
        $self->events = [];

        return $self;
    }

    public function __construct(iterable $attributes)
    {
        $this->id = uuid_create();
        $this->attributes = $attributes;
        $this->events[] = new ProductCreated($this->id, $attributes);
        foreach ($attributes as $attribute) {
            $this->events[] = new AttributeAddedToProduct($this, $attribute);
        }
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function __get($name)
    {
        return $this->$name;
    }
}

final class Attribute implements HasEvents
{
    use PopEvents;

    private $id;
    private $family;

    public static function fromResultSet(array $resultSet): self
    {
        $self = new self(Family::fromResultSet($resultSet['family']));
        $self->id = $resultSet['attribute_id'];
        $self->events = [];

        return $self;
    }

    public function __construct(Family $family)
    {
        $this->id = uuid_create();
        $this->family = $family;
        $this->events[] = new AttributeCreated($this->id, $this->family);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getFamily(): Family
    {
        return $this->family;
    }
}

final class Family implements HasEvents
{
    use PopEvents;

    private $id;
    private $name;

    public static function fromResultSet(array $resultSet): self
    {
        $self = new self($resultSet['name']);
        $self->id = $resultSet['family_id'];
        $self->events = [];

        return $self;
    }

    public function __construct(string $name)
    {
        $this->id = uuid_create();
        $this->name = $name;
        $this->events[] = new FamilyCreated($this->id, $this->name);
    }

    public function rename($name): void
    {
        $this->name = $name;
        $this->events[] = new FamilyRenamed($this->id, $this->name);
    }

    public function getId(): string
    {
        return $this->id;
    }
}

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
                $eventName = (new \ReflectionObject($event))->getShortName();
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
                }
            }
            $transaction->commit();
        }
        $this->streams = [];
    }
}

$connection = new \pq\Connection('dbname=florian user=florian host=localhost');

$uow = new WriteProjection($connection);
$products = new Products($connection, $uow);

//$p = new Product([new Attribute(new Family('family1')), new Attribute(new Family('family1'))]);
//$products->add($p);
//
//$uow->commit();
//
//$product1 = $products->find($p->getId());
//
//$family1->rename('ae');
//$products->add($product1);
//$uow->commit();

//$product1 = $products->find($argv[1]);
//var_dump(iterator_to_array($product1->attributes));
//var_dump(iterator_to_array($product1->attributes));

//$product1->attributes[0]->getFamily()->rename('AAAAAA');
//$products->add($product1);
//$uow->commit();

$product1 = $products->find($argv[1]);
var_dump($product1);

//for($i=0; $i < 10000; $i++) {
//    $p = new Product([new Attribute(new Family('family1')), new Attribute(new Family('family1'))]);
//    $products->add($p);
//
//    ($i % 100 == 0) && $uow->commit();
//}

foreach ($products->findAll2() as $product) {
    var_dump(iterator_to_array($product->attributes));
}
