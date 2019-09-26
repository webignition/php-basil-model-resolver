<?php

namespace webignition\BasilModelResolver;

use webignition\BasilModel\Assertion\AssertionInterface;
use webignition\BasilModel\Assertion\ComparisonAssertionInterface;
use webignition\BasilModel\Assertion\ExaminationAssertionInterface;
use webignition\BasilModel\Identifier\IdentifierCollectionInterface;
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
            $examinedValue = $assertion->getExaminedValue();
            $resolvedValue = $this->valueResolver->resolve($examinedValue, $pageProvider, $identifierCollection);
            $assertion = $assertion->withExaminedValue($resolvedValue);
        }

        if ($assertion instanceof ComparisonAssertionInterface) {
            $expectedValue = $assertion->getExpectedValue();
            $resolvedValue = $this->valueResolver->resolve($expectedValue, $pageProvider, $identifierCollection);

            $assertion = $assertion->withExpectedValue($resolvedValue);
        }

        return $assertion;
    }
}
