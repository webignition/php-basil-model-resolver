<?php

namespace webignition\BasilModelResolver;

use webignition\BasilContextAwareException\ContextAwareExceptionInterface;
use webignition\BasilContextAwareException\ContextAwareExceptionTrait;
use webignition\BasilContextAwareException\ExceptionContext\ExceptionContext;

class UnknownPageElementException extends \Exception implements ContextAwareExceptionInterface
{
    use ContextAwareExceptionTrait;

    private $importName;
    private $elementName;

    public function __construct(string $importName, string $elementName)
    {
        parent::__construct('Unknown page element "' . $elementName . '" in page "' . $importName . '"');

        $this->importName = $importName;
        $this->elementName = $elementName;
        $this->exceptionContext = new ExceptionContext();
    }

    public function getImportName(): string
    {
        return $this->importName;
    }

    public function getElementName(): string
    {
        return $this->elementName;
    }
}
