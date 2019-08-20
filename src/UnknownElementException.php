<?php

namespace webignition\BasilModelResolver;

use webignition\BasilContextAwareException\ContextAwareExceptionInterface;
use webignition\BasilContextAwareException\ContextAwareExceptionTrait;
use webignition\BasilContextAwareException\ExceptionContext\ExceptionContext;

class UnknownElementException extends \Exception implements ContextAwareExceptionInterface
{
    use ContextAwareExceptionTrait;

    private $elementName;

    public function __construct(string $elementName)
    {
        parent::__construct('Unknown element "' . $elementName. '"');

        $this->elementName = $elementName;
        $this->exceptionContext = new ExceptionContext();
    }

    public function getElementName(): string
    {
        return $this->elementName;
    }
}
