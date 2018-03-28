<?php declare(strict_types=1);

namespace DocteurKlein\ORMish;

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
        if (!$this->consumed) {
            $this->cache = iterator_to_array($this->generator);
        }
        return $this->cache[$offset];
    }

    public function offsetSet($offset, $value)
    {
        if (!$this->consumed) {
            $this->cache = iterator_to_array($this->generator);
        }
        reset($this->cache);
        return $this->cache[$offset] = $value;
    }

    public function offsetExists($offset)
    {
        if (!$this->consumed) {
            $this->cache = iterator_to_array($this->generator);
        }
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

