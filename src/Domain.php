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
