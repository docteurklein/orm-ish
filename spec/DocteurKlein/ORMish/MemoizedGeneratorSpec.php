<?php

namespace spec\DocteurKlein\ORMish;

use DocteurKlein\ORMish\MemoizedGenerator;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class MemoizedGeneratorSpec extends ObjectBehavior
{
    function let()
    {
        $this->beConstructedWith((function() {
            foreach([0,1,2,3] as $i) {
                yield $i;
            }
        })());
    }

    function it_regenerates()
    {
        $v1 = iterator_to_array($this->getWrappedObject());
        $v2 = iterator_to_array($this->getWrappedObject());

        if ($v1 !== $v2) {
            throw new \Exception;
        }
        if ($v1 !== [0,1,2,3]) {
            throw new \Exception;
        }
    }

    function it_accesses_offsets()
    {
        $this[0]->shouldBe(0);
    }
}
