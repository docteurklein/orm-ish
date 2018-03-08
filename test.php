<?php declare(strict_types=1);

namespace DocteurKlein\ORM;

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

final class ProductAdded
{
    public $id;
    public $attributes;

    public function __construct($id, $attributes)
    {
        $this->id = $id;
        $this->attributes = $attributes;
    }
}

final class AttributeAdded
{
    public $id;
    public $family;

    public function __construct($id, $family)
    {
        $this->id = $id;
        $this->family = $family;
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

final class Product implements HasEvents
{
    use PopEvents;

    private $id;
    private $attributes;

    public function __construct(iterable $attributes)
    {
        $this->id = uniqid();
        $this->attributes = $attributes;
        $this->events[] = new ProductAdded($this->id, $attributes);
    }
}

final class Attribute implements HasEvents
{
    use PopEvents;

    private $id;
    private $family;

    public function __construct(Family $family)
    {
        $this->id = uniqid();
        $this->family = $family;
        $this->events[] = new AttributeAdded($this->id, $this->family);
    }
}

final class Family implements HasEvents
{
    use PopEvents;

    private $id;
    private $name;

    public function __construct(string $name)
    {
        $this->id = uniqid();
        $this->name = $name;
        $this->events[] = new FamilyCreated($this->id, $this->name);
    }
}

final class Products
{
    private $eventStreams;

    public function __construct(iterable $eventStreams)
    {
        $this->eventStreams = $eventStreams;
    }

    public function find($id): Product
    {
        $resultset = [
            1
        ];

        $generator = $this->hydrate(Product::class, $resultset);

        return $generator->current();
    }

    private function hydrate(string $class, iterable $resultset): iterable
    {
        $reflect = new \ReflectionClass($class);
        foreach ($resultset as $row) {
            $instance = $reflect->newInstanceWithoutConstructor();

            yield $instance;
        }
    }

    public function add(Product $product): void
    {
        $this->eventStreams[] = $product->popEvents();
    }
}

$products = new Products($eventStreams = new \ArrayObject([]));

$products->add(new Product([$attribute1 = new Attribute(new Family('family1'))]));

$product1 = $products->find(1);

foreach($eventStreams as $stream) {
    var_dump(iterator_to_array($stream, false));
}

