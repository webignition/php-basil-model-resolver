<?php

namespace webignition\BasilModelResolver;

use webignition\BasilModel\Assertion\AssertionInterface;
use webignition\BasilModel\Assertion\ComparisonAssertionInterface;
use webignition\BasilModel\Assertion\ExaminationAssertionInterface;
use webignition\BasilModel\Exception\InvalidAssertionExaminedValueException;
use webignition\BasilModel\Exception\InvalidAssertionExpectedValueException;
use webignition\BasilModel\Identifier\IdentifierCollectionInterface;
use webignition\BasilModel\Value\Assertion\ExaminedValue;
use webignition\BasilModel\Value\Assertion\ExpectedValue;
use webignition\BasilModelProvider\Exception\UnknownPageException;
use webignition\BasilModelProvider\Page\PageProviderInterface;

class AssertionResolver
{
    private $valueResolver;

    public function __construct(ValueResolver $valueResolver)
    {
        $this->valueResolver = $valueResolver;
    }

    public static function createResolver(): AssertionResolver
    {
        return new AssertionResolver(
            ValueResolver::createResolver()
        );
    }

    /**
     * @param AssertionInterface $assertion
     * @param PageProviderInterface $pageProvider
     * @param IdentifierCollectionInterface $identifierCollection
     *
     * @return AssertionInterface
     *
     * @throws InvalidAssertionExaminedValueException
     * @throws InvalidAssertionExpectedValueException
     * @throws UnknownElementException
     * @throws UnknownPageElementException
     * @throws UnknownPageException
     */
    public function resolve(
        AssertionInterface $assertion,
        PageProviderInterface $pageProvider,
        IdentifierCollectionInterface $identifierCollection
    ): AssertionInterface {
        if ($assertion instanceof ExaminationAssertionInterface) {
            $examinedValue = $assertion->getExaminedValue()->getExaminedValue();
            $resolvedValue = $this->valueResolver->resolve($examinedValue, $pageProvider, $identifierCollection);
            $assertion = $assertion->withExaminedValue(new ExaminedValue($resolvedValue));
        }

        if ($assertion instanceof ComparisonAssertionInterface) {
            $expectedValue = $assertion->getExpectedValue()->getExpectedValue();
            $resolvedValue = $this->valueResolver->resolve($expectedValue, $pageProvider, $identifierCollection);

            $assertion = $assertion->withExpectedValue(new ExpectedValue($resolvedValue));
        }

        return $assertion;
    }
}
