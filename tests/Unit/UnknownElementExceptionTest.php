<?php

namespace webignition\BasilModelResolver\Tests\Unit;

use webignition\BasilModelResolver\UnknownElementException;

class UnknownElementExceptionTest extends \PHPUnit\Framework\TestCase
{
    public function testCreate()
    {
        $elementName = 'element_name';

        $exception = new UnknownElementException($elementName);

        $this->assertSame('Unknown element "element_name"', $exception->getMessage());
        $this->assertSame($elementName, $exception->getElementName());
    }
}
