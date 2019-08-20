<?php
/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection PhpDocSignatureInspection */

namespace webignition\BasilModelResolver\Tests\Unit;

use Nyholm\Psr7\Uri;
use webignition\BasilModel\Action\ActionTypes;
use webignition\BasilModel\Action\InteractionAction;
use webignition\BasilModel\Assertion\Assertion;
use webignition\BasilModel\Assertion\AssertionComparisons;
use webignition\BasilModel\DataSet\DataSet;
use webignition\BasilModel\DataSet\DataSetCollection;
use webignition\BasilModel\Identifier\Identifier;
use webignition\BasilModel\Identifier\IdentifierCollection;
use webignition\BasilModel\Identifier\IdentifierTypes;
use webignition\BasilModel\Page\Page;
use webignition\BasilModel\Step\PendingImportResolutionStep;
use webignition\BasilModel\Step\Step;
use webignition\BasilModel\Test\Configuration;
use webignition\BasilModel\Test\Test;
use webignition\BasilModel\TestSuite\TestSuite;
use webignition\BasilModel\TestSuite\TestSuiteInterface;
use webignition\BasilModel\Value\ElementValue;
use webignition\BasilModel\Value\ObjectValue;
use webignition\BasilModel\Value\ValueTypes;
use webignition\BasilModelProvider\DataSet\DataSetProvider;
use webignition\BasilModelProvider\DataSet\DataSetProviderInterface;
use webignition\BasilModelProvider\DataSet\EmptyDataSetProvider;
use webignition\BasilModelProvider\Page\EmptyPageProvider;
use webignition\BasilModelProvider\Page\PageProvider;
use webignition\BasilModelProvider\Page\PageProviderInterface;
use webignition\BasilModelProvider\Step\EmptyStepProvider;
use webignition\BasilModelProvider\Step\StepProvider;
use webignition\BasilModelProvider\Step\StepProviderInterface;
use webignition\BasilModelResolver\TestSuiteResolver;
use webignition\BasilTestIdentifierFactory\TestIdentifierFactory;

class TestSuiteResolverTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var TestSuiteResolver
     */
    private $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = TestSuiteResolver::createResolver();
    }

    /**
     * @dataProvider resolveDataProvider
     */
    public function testResolve(
        TestSuiteInterface $testSuite,
        PageProviderInterface $pageProvider,
        StepProviderInterface $stepProvider,
        DataSetProviderInterface $dataSetProvider,
        TestSuiteInterface $expectedTestSuite
    ) {
        $resolvedTest = $this->resolver->resolve($testSuite, $pageProvider, $stepProvider, $dataSetProvider);

        $this->assertEquals($expectedTestSuite, $resolvedTest);
    }

    public function resolveDataProvider(): array
    {
        return [
            'single empty test' => [
                'testSuite' => new TestSuite(
                    'test suite name',
                    [new Test('test name', new Configuration('', ''), [])]
                ),
                'pageProvider' => new EmptyPageProvider(),
                'stepProvider' => new EmptyStepProvider(),
                'dataSetProvider' => new EmptyDataSetProvider(),
                'expectedTestSuite' => new TestSuite(
                    'test suite name',
                    [new Test('test name', new Configuration('', ''), [])]
                ),
            ],
            'test is resolved' => [
                'testSuite' => new TestSuite(
                    'test suite name',
                    [
                        new Test(
                            'test name',
                            new Configuration('', ''),
                            [
                                'step name' => (new PendingImportResolutionStep(
                                    new Step([], []),
                                    'step_import_name',
                                    'data_provider_import_name'
                                ))->withIdentifierCollection(new IdentifierCollection([
                                    TestIdentifierFactory::createPageElementReferenceIdentifier(
                                        new ObjectValue(
                                            ValueTypes::STRING,
                                            'page_import_name.elements.heading_element_name',
                                            'page_import_name',
                                            'heading_element_name'
                                        ),
                                        'heading'
                                    ),
                                ])),
                            ]
                        )
                    ]
                ),
                'pageProvider' => new PageProvider([
                    'page_import_name' => new Page(
                        new Uri('http://example.com/'),
                        new IdentifierCollection([
                            TestIdentifierFactory::createCssElementIdentifier(
                                '.action-selector',
                                1,
                                'action_element_name'
                            ),
                            TestIdentifierFactory::createCssElementIdentifier(
                                '.assertion-selector',
                                1,
                                'assertion_element_name'
                            ),
                            TestIdentifierFactory::createCssElementIdentifier(
                                '.heading-selector',
                                1,
                                'heading_element_name'
                            ),
                        ])
                    )
                ]),
                'stepProvider' => new StepProvider([
                    'step_import_name' => new Step(
                        [
                            new InteractionAction(
                                'click page_import_name.elements.action_element_name',
                                ActionTypes::CLICK,
                                new Identifier(
                                    IdentifierTypes::PAGE_ELEMENT_REFERENCE,
                                    new ObjectValue(
                                        ValueTypes::PAGE_ELEMENT_REFERENCE,
                                        'page_import_name.elements.action_element_name',
                                        'page_import_name',
                                        'action_element_name'
                                    )
                                ),
                                'page_import_name.elements.action_element_name'
                            )
                        ],
                        [
                            new Assertion(
                                'page_import_name.elements.assertion_element_name exists',
                                new ObjectValue(
                                    ValueTypes::PAGE_ELEMENT_REFERENCE,
                                    'page_import_name.elements.assertion_element_name',
                                    'page_import_name',
                                    'assertion_element_name'
                                ),
                                AssertionComparisons::EXISTS
                            )
                        ]
                    ),
                ]),
                'dataSetProvider' => new DataSetProvider([
                    'data_provider_import_name' => new DataSetCollection([
                        new DataSet('0', [
                            'foo' => 'bar',
                        ]),
                    ]),
                ]),
                'expectedTestSuite' => new TestSuite(
                    'test suite name',
                    [
                        new Test(
                            'test name',
                            new Configuration('', ''),
                            [
                                'step name' => (new Step(
                                    [
                                        new InteractionAction(
                                            'click page_import_name.elements.action_element_name',
                                            ActionTypes::CLICK,
                                            TestIdentifierFactory::createCssElementIdentifier(
                                                '.action-selector',
                                                1,
                                                'action_element_name'
                                            ),
                                            'page_import_name.elements.action_element_name'
                                        )
                                    ],
                                    [
                                        new Assertion(
                                            'page_import_name.elements.assertion_element_name exists',
                                            new ElementValue(
                                                TestIdentifierFactory::createCssElementIdentifier(
                                                    '.assertion-selector',
                                                    1,
                                                    'assertion_element_name'
                                                )
                                            ),
                                            AssertionComparisons::EXISTS
                                        )
                                    ]
                                ))->withDataSetCollection(new DataSetCollection([
                                    new DataSet('0', [
                                        'foo' => 'bar',
                                    ]),
                                ])),
                            ]
                        )
                    ]
                ),
            ],
        ];
    }
}
