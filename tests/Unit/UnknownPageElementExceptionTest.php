<?php

namespace webignition\BasilModelResolver\Tests\Unit;

use webignition\BasilModelResolver\UnknownPageElementException;

class UnknownPageElementExceptionTest extends \PHPUnit\Framework\TestCase
{
    public function testCreate()
    {
        $importName = 'page_import_name';
        $elementName = 'element_name';

        $exception = new UnknownPageElementException($importName, $elementName);

        $this->assertSame('Unknown page element "element_name" in page "page_import_name"', $exception->getMessage());
        $this->assertSame($importName, $exception->getImportName());
        $this->assertSame($elementName, $exception->getElementName());
    }
}
