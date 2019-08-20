<?php

namespace webignition\BasilModelResolver;

use webignition\BasilModel\TestSuite\TestSuite;
use webignition\BasilModel\TestSuite\TestSuiteInterface;
use webignition\BasilModelProvider\DataSet\DataSetProviderInterface;
use webignition\BasilModelProvider\Exception\UnknownDataProviderException;
use webignition\BasilModelProvider\Exception\UnknownPageException;
use webignition\BasilModelProvider\Exception\UnknownStepException;
use webignition\BasilModelProvider\Page\PageProviderInterface;
use webignition\BasilModelProvider\Step\StepProviderInterface;

class TestSuiteResolver
{
    private $testResolver;

    public function __construct(TestResolver $testResolver)
    {
        $this->testResolver = $testResolver;
    }

    public static function createResolver(): TestSuiteResolver
    {
        return new TestSuiteResolver(
            TestResolver::createResolver()
        );
    }

    /**
     * @param TestSuiteInterface $testSuite
     * @param PageProviderInterface $pageProvider
     * @param StepProviderInterface $stepProvider
     * @param DataSetProviderInterface $dataSetProvider
     *
     * @return TestSuiteInterface
     *
     * @throws CircularStepImportException
     * @throws UnknownDataProviderException
     * @throws UnknownElementException
     * @throws UnknownPageElementException
     * @throws UnknownPageException
     * @throws UnknownStepException
     */
    public function resolve(
        TestSuiteInterface $testSuite,
        PageProviderInterface $pageProvider,
        StepProviderInterface $stepProvider,
        DataSetProviderInterface $dataSetProvider
    ): TestSuiteInterface {
        $resolvedTests = [];

        foreach ($testSuite->getTests() as $test) {
            $resolvedTests[] = $this->testResolver->resolve($test, $pageProvider, $stepProvider, $dataSetProvider);
        }

        return new TestSuite($testSuite->getName(), $resolvedTests);
    }
}
