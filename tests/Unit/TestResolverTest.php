<?php
/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection PhpDocSignatureInspection */

namespace webignition\BasilModelResolver\Tests\Unit;

use Nyholm\Psr7\Uri;
use webignition\BasilContextAwareException\ContextAwareExceptionInterface;
use webignition\BasilContextAwareException\ExceptionContext\ExceptionContext;
use webignition\BasilContextAwareException\ExceptionContext\ExceptionContextInterface;
use webignition\BasilModel\Action\ActionTypes;
use webignition\BasilModel\Action\InputAction;
use webignition\BasilModel\Action\InteractionAction;
use webignition\BasilModel\Assertion\AssertionComparison;
use webignition\BasilModel\Assertion\ComparisonAssertion;
use webignition\BasilModel\Assertion\ExaminationAssertion;
use webignition\BasilModel\DataSet\DataSet;
use webignition\BasilModel\DataSet\DataSetCollection;
use webignition\BasilModel\Identifier\DomIdentifier;
use webignition\BasilModel\Identifier\IdentifierCollection;
use webignition\BasilModel\Page\Page;
use webignition\BasilModel\Step\PendingImportResolutionStep;
use webignition\BasilModel\Step\Step;
use webignition\BasilModel\Test\Configuration;
use webignition\BasilModel\Test\Test;
use webignition\BasilModel\Test\TestInterface;
use webignition\BasilModel\Value\Assertion\ExaminedValue;
use webignition\BasilModel\Value\Assertion\ExpectedValue;
use webignition\BasilModel\Value\DomIdentifierValue;
use webignition\BasilModel\Value\ObjectValue;
use webignition\BasilModel\Value\ObjectValueType;
use webignition\BasilModel\Value\PageElementReference;
use webignition\BasilModelFactory\Action\ActionFactory;
use webignition\BasilModelFactory\AssertionFactory;
use webignition\BasilModelProvider\DataSet\DataSetProvider;
use webignition\BasilModelProvider\DataSet\DataSetProviderInterface;
use webignition\BasilModelProvider\DataSet\EmptyDataSetProvider;
use webignition\BasilModelProvider\Exception\UnknownDataProviderException;
use webignition\BasilModelProvider\Exception\UnknownPageException;
use webignition\BasilModelProvider\Exception\UnknownStepException;
use webignition\BasilModelProvider\Page\EmptyPageProvider;
use webignition\BasilModelProvider\Page\PageProvider;
use webignition\BasilModelProvider\Page\PageProviderInterface;
use webignition\BasilModelProvider\Step\EmptyStepProvider;
use webignition\BasilModelProvider\Step\StepProvider;
use webignition\BasilModelProvider\Step\StepProviderInterface;
use webignition\BasilModelResolver\TestResolver;
use webignition\BasilModelResolver\UnknownElementException;
use webignition\BasilModelResolver\UnknownPageElementException;
use webignition\BasilTestIdentifierFactory\TestIdentifierFactory;

class TestResolverTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var TestResolver
     */
    private $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = TestResolver::createResolver();
    }

    /**
     * @dataProvider resolveSuccessDataProvider
     */
    public function testResolveSuccess(
        TestInterface $test,
        PageProviderInterface $pageProvider,
        StepProviderInterface $stepProvider,
        DataSetProviderInterface $dataSetProvider,
        TestInterface $expectedTest
    ) {
        $resolvedTest = $this->resolver->resolve($test, $pageProvider, $stepProvider, $dataSetProvider);

        $this->assertEquals($expectedTest, $resolvedTest);
    }

    public function resolveSuccessDataProvider(): array
    {
        $actionFactory = ActionFactory::createFactory();
        $assertionFactory = AssertionFactory::createFactory();

        $actionSelectorIdentifier = new DomIdentifier('.action-selector');
        $assertionSelectorIdentifier = new DomIdentifier('.assertion-selector');

        $namedActionSelectorIdentifier = TestIdentifierFactory::createElementIdentifier(
            '.action-selector',
            1,
            'action_selector'
        );

        $namedAssertionSelectorIdentifier = TestIdentifierFactory::createElementIdentifier(
            '.assertion-selector',
            1,
            'assertion_selector'
        );

        $pageElementReferenceActionIdentifier = TestIdentifierFactory::createPageElementReferenceIdentifier(
            new PageElementReference(
                'page_import_name.elements.action_selector',
                'page_import_name',
                'action_selector'
            ),
            'action_selector'
        );

        $pageElementReferenceAssertionIdentifier = TestIdentifierFactory::createPageElementReferenceIdentifier(
            new PageElementReference(
                'page_import_name.elements.assertion_selector',
                'page_import_name',
                'assertion_selector'
            ),
            'assertion_selector'
        );

        $expectedResolvedDataTest = new Test('test name', new Configuration('', ''), [
            'step name' => (new Step(
                [
                    new InputAction(
                        'set ".action-selector" to $data.key1',
                        $actionSelectorIdentifier,
                        new ObjectValue(ObjectValueType::DATA_PARAMETER, '$data.key1', 'key1'),
                        '".action-selector" to $data.key1'
                    )
                ],
                [
                    new ComparisonAssertion(
                        '".assertion-selector" is $data.key2',
                        new ExaminedValue(new DomIdentifierValue($assertionSelectorIdentifier)),
                        AssertionComparison::IS,
                        new ExpectedValue(new ObjectValue(ObjectValueType::DATA_PARAMETER, '$data.key2', 'key2'))
                    )
                ]
            ))->withDataSetCollection(new DataSetCollection([
                new DataSet('0', [
                    'key1' => 'key1value1',
                    'key2' => 'key2value1',
                ]),
                new DataSet('1', [
                    'key1' => 'key1value2',
                    'key2' => 'key2value2',
                ]),
            ])),
        ]);

        return [
            'empty test' => [
                'test' => new Test('test name', new Configuration('', ''), []),
                'pageProvider' => new EmptyPageProvider(),
                'stepProvider' => new EmptyStepProvider(),
                'dataSetProvider' => new EmptyDataSetProvider(),
                'expectedTest' => new Test('test name', new Configuration('', ''), []),
            ],
            'configuration is resolved' => [
                'test' => new Test(
                    'test name',
                    new Configuration('', 'page_import_name.url'),
                    []
                ),
                'pageProvider' => new PageProvider([
                    'page_import_name' => new Page(new Uri('http://example.com/'), new IdentifierCollection()),
                ]),
                'stepProvider' => new EmptyStepProvider(),
                'dataSetProvider' => new EmptyDataSetProvider(),
                'expectedTest' => new Test(
                    'test name',
                    new Configuration('', 'http://example.com/'),
                    []
                ),
            ],
            'empty step' => [
                'test' => new Test('test name', new Configuration('', ''), [
                    'step name' => new Step([], []),
                ]),
                'pageProvider' => new EmptyPageProvider(),
                'stepProvider' => new EmptyStepProvider(),
                'dataSetProvider' => new EmptyDataSetProvider(),
                'expectedTest' => new Test('test name', new Configuration('', ''), [
                    'step name' => new Step([], []),
                ]),
            ],
            'no imports, actions and assertions require no resolution' => [
                'test' => new Test(
                    'test name',
                    new Configuration('', ''),
                    [
                        'step name' => new Step(
                            [
                                $actionFactory->createFromActionString('click ".action-selector"'),
                            ],
                            [
                                $assertionFactory->createFromAssertionString('".assertion-selector" exists')
                            ]
                        ),
                    ]
                ),
                'pageProvider' => new EmptyPageProvider(),
                'stepProvider' => new EmptyStepProvider(),
                'dataSetProvider' => new EmptyDataSetProvider(),
                'expectedTest' => new Test('test name', new Configuration('', ''), [
                    'step name' => new Step(
                        [
                            new InteractionAction(
                                'click ".action-selector"',
                                ActionTypes::CLICK,
                                $actionSelectorIdentifier,
                                '".action-selector"'
                            )
                        ],
                        [
                            new ExaminationAssertion(
                                '".assertion-selector" exists',
                                new ExaminedValue(new DomIdentifierValue($assertionSelectorIdentifier)),
                                AssertionComparison::EXISTS
                            )
                        ]
                    ),
                ]),
            ],
            'actions and assertions require resolution of page imports' => [
                'test' => new Test(
                    'test name',
                    new Configuration('', ''),
                    [
                        'step name' => new Step(
                            [
                                $actionFactory->createFromActionString(
                                    'click page_import_name.elements.action_selector'
                                ),
                            ],
                            [
                                $assertionFactory->createFromAssertionString(
                                    'page_import_name.elements.assertion_selector exists'
                                )
                            ]
                        ),
                    ]
                ),
                'pageProvider' => new PageProvider([
                    'page_import_name' => new Page(
                        new Uri('http://example.com'),
                        new IdentifierCollection([
                            $namedActionSelectorIdentifier,
                            $namedAssertionSelectorIdentifier,
                        ])
                    ),
                ]),
                'stepProvider' => new EmptyStepProvider(),
                'dataSetProvider' => new EmptyDataSetProvider(),
                'expectedTest' => new Test('test name', new Configuration('', ''), [
                    'step name' => new Step(
                        [
                            new InteractionAction(
                                'click page_import_name.elements.action_selector',
                                ActionTypes::CLICK,
                                $namedActionSelectorIdentifier,
                                'page_import_name.elements.action_selector'
                            )
                        ],
                        [
                            new ExaminationAssertion(
                                'page_import_name.elements.assertion_selector exists',
                                new ExaminedValue(new DomIdentifierValue($namedAssertionSelectorIdentifier)),
                                AssertionComparison::EXISTS
                            )
                        ]
                    ),
                ]),
            ],
            'empty step imports step, imported actions and assertions require no resolution' => [
                'test' => new Test(
                    'test name',
                    new Configuration('', ''),
                    [
                        'step name' => new PendingImportResolutionStep(
                            new Step([], []),
                            'step_import_name',
                            ''
                        ),
                    ]
                ),
                'pageProvider' => new EmptyPageProvider(),
                'stepProvider' => new StepProvider([
                    'step_import_name' => new Step(
                        [
                            $actionFactory->createFromActionString('click ".action-selector"'),
                        ],
                        [
                            $assertionFactory->createFromAssertionString('".assertion-selector" exists')
                        ]
                    )
                ]),
                'dataSetProvider' => new EmptyDataSetProvider(),
                'expectedTest' => new Test('test name', new Configuration('', ''), [
                    'step name' => new Step(
                        [
                            new InteractionAction(
                                'click ".action-selector"',
                                ActionTypes::CLICK,
                                $actionSelectorIdentifier,
                                '".action-selector"'
                            )
                        ],
                        [
                            new ExaminationAssertion(
                                '".assertion-selector" exists',
                                new ExaminedValue(new DomIdentifierValue($assertionSelectorIdentifier)),
                                AssertionComparison::EXISTS
                            )
                        ]
                    ),
                ]),
            ],
            'empty step imports step, imported actions and assertions require element resolution' => [
                'test' => new Test(
                    'test name',
                    new Configuration('', ''),
                    [
                        'step name' => (new PendingImportResolutionStep(
                            new Step([], []),
                            'step_import_name',
                            ''
                        ))->withIdentifierCollection(new IdentifierCollection([
                            $pageElementReferenceActionIdentifier,
                            $pageElementReferenceAssertionIdentifier,
                        ])),
                    ]
                ),
                'pageProvider' => new PageProvider([
                    'page_import_name' => new Page(
                        new Uri('http://example.com'),
                        new IdentifierCollection([
                            $namedActionSelectorIdentifier,
                            $namedAssertionSelectorIdentifier,
                        ])
                    ),
                ]),
                'stepProvider' => new StepProvider([
                    'step_import_name' => new Step(
                        [
                            $actionFactory->createFromActionString('click $elements.action_selector'),
                        ],
                        [
                            $assertionFactory->createFromAssertionString('$elements.assertion_selector exists')
                        ]
                    )
                ]),
                'dataSetProvider' => new EmptyDataSetProvider(),
                'expectedTest' => new Test('test name', new Configuration('', ''), [
                    'step name' => new Step(
                        [
                            new InteractionAction(
                                'click $elements.action_selector',
                                ActionTypes::CLICK,
                                $namedActionSelectorIdentifier,
                                '$elements.action_selector'
                            )
                        ],
                        [
                            new ExaminationAssertion(
                                '$elements.assertion_selector exists',
                                new ExaminedValue(new DomIdentifierValue($namedAssertionSelectorIdentifier)),
                                AssertionComparison::EXISTS
                            )
                        ]
                    ),
                ]),
            ],
            'empty step imports step, imported actions and assertions use inline data' => [
                'test' => new Test(
                    'test name',
                    new Configuration('', ''),
                    [
                        'step name' => (new PendingImportResolutionStep(
                            new Step([], []),
                            'step_import_name',
                            ''
                        ))->withDataSetCollection(new DataSetCollection([
                            new DataSet('0', [
                                'key1' => 'key1value1',
                                'key2' => 'key2value1',
                            ]),
                            new DataSet('1', [
                                'key1' => 'key1value2',
                                'key2' => 'key2value2',
                            ]),
                        ])),
                    ]
                ),
                'pageProvider' => new EmptyPageProvider(),
                'stepProvider' => new StepProvider([
                    'step_import_name' => new Step(
                        [
                            $actionFactory->createFromActionString('set ".action-selector" to $data.key1'),
                        ],
                        [
                            $assertionFactory->createFromAssertionString('".assertion-selector" is $data.key2')
                        ]
                    )
                ]),
                'dataSetProvider' => new EmptyDataSetProvider(),
                'expectedTest' => $expectedResolvedDataTest,
            ],
            'empty step imports step, imported actions and assertions use imported data' => [
                'test' => new Test(
                    'test name',
                    new Configuration('', ''),
                    [
                        'step name' => new PendingImportResolutionStep(
                            new Step([], []),
                            'step_import_name',
                            'data_provider_import_name'
                        ),
                    ]
                ),
                'pageProvider' => new EmptyPageProvider(),
                'stepProvider' => new StepProvider([
                    'step_import_name' => new Step(
                        [
                            $actionFactory->createFromActionString('set ".action-selector" to $data.key1'),
                        ],
                        [
                            $assertionFactory->createFromAssertionString('".assertion-selector" is $data.key2')
                        ]
                    )
                ]),
                'dataSetProvider' => new DataSetProvider([
                    'data_provider_import_name' => new DataSetCollection([
                        new DataSet('0', [
                            'key1' => 'key1value1',
                            'key2' => 'key2value1',
                        ]),
                        new DataSet('1', [
                            'key1' => 'key1value2',
                            'key2' => 'key2value2',
                        ]),
                    ]),
                ]),
                'expectedTest' => $expectedResolvedDataTest,
            ],
            'deferred step import, imported actions and assertions require element resolution' => [
                'test' => new Test(
                    'test name',
                    new Configuration('', ''),
                    [
                        'step name' => (new PendingImportResolutionStep(
                            new Step([], []),
                            'step_import_name',
                            ''
                        ))->withIdentifierCollection(new IdentifierCollection([
                            $pageElementReferenceActionIdentifier,
                            $pageElementReferenceAssertionIdentifier,
                        ])),
                    ]
                ),
                'pageProvider' => new PageProvider([
                    'page_import_name' => new Page(
                        new Uri('https://example.com'),
                        new IdentifierCollection([
                            TestIdentifierFactory::createElementIdentifier(
                                '.action-selector',
                                1,
                                'action_selector'
                            ),
                            TestIdentifierFactory::createElementIdentifier(
                                '.assertion-selector',
                                1,
                                'assertion_selector'
                            ),
                        ])
                    ),
                ]),
                'stepProvider' => new StepProvider([
                    'step_import_name' => new PendingImportResolutionStep(
                        new Step([], []),
                        'deferred',
                        ''
                    ),
                    'deferred' => new Step(
                        [
                            new InteractionAction(
                                'click $elements.action_selector',
                                ActionTypes::CLICK,
                                $namedActionSelectorIdentifier,
                                '$elements.action_selector'
                            ),
                        ],
                        [
                            new ExaminationAssertion(
                                '$elements.assertion_selector exists',
                                new ExaminedValue(new DomIdentifierValue($namedAssertionSelectorIdentifier)),
                                AssertionComparison::EXISTS
                            ),
                        ]
                    ),
                ]),
                'dataSetProvider' => new EmptyDataSetProvider(),
                'expectedTest' => new Test('test name', new Configuration('', ''), [
                    'step name' => new Step(
                        [
                            new InteractionAction(
                                'click $elements.action_selector',
                                ActionTypes::CLICK,
                                $namedActionSelectorIdentifier,
                                '$elements.action_selector'
                            ),
                        ],
                        [
                            new ExaminationAssertion(
                                '$elements.assertion_selector exists',
                                new ExaminedValue(new DomIdentifierValue($namedAssertionSelectorIdentifier)),
                                AssertionComparison::EXISTS
                            ),
                        ]
                    ),
                ]),
            ],
            'deferred step import, imported actions and assertions use imported data' => [
                'test' => new Test(
                    'test name',
                    new Configuration('', ''),
                    [
                        'step name' => (new PendingImportResolutionStep(
                            new Step([], []),
                            'step_import_name',
                            'data_provider_import_name'
                        ))->withIdentifierCollection(new IdentifierCollection([
                            $pageElementReferenceActionIdentifier,
                            $pageElementReferenceAssertionIdentifier,
                        ])),
                    ]
                ),
                'pageProvider' => new PageProvider([
                    'page_import_name' => new Page(
                        new Uri('https://example.com'),
                        new IdentifierCollection([
                            TestIdentifierFactory::createElementIdentifier(
                                '.action-selector',
                                1,
                                'action_selector'
                            ),
                            TestIdentifierFactory::createElementIdentifier(
                                '.assertion-selector',
                                1,
                                'assertion_selector'
                            ),
                        ])
                    ),
                ]),
                'stepProvider' => new StepProvider([
                    'step_import_name' => new PendingImportResolutionStep(
                        new Step([], []),
                        'deferred',
                        ''
                    ),
                    'deferred' => new Step(
                        [
                            new InputAction(
                                'set $elements.action_selector to $data.key1',
                                $namedActionSelectorIdentifier,
                                new ObjectValue(ObjectValueType::DATA_PARAMETER, '$data.key1', 'key1'),
                                '$elements.action_selector to $data.key1'
                            )
                        ],
                        [
                            new ComparisonAssertion(
                                '$elements.assertion_selector is $data.key2',
                                new ExaminedValue(new DomIdentifierValue($namedAssertionSelectorIdentifier)),
                                AssertionComparison::IS,
                                new ExpectedValue(
                                    new ObjectValue(ObjectValueType::DATA_PARAMETER, '$data.key2', 'key2')
                                )
                            )
                        ]
                    ),
                ]),
                'dataSetProvider' => new DataSetProvider([
                    'data_provider_import_name' => new DataSetCollection([
                        new DataSet('0', [
                            'key1' => 'key1value1',
                            'key2' => 'key2value1',
                        ]),
                        new DataSet('1', [
                            'key1' => 'key1value2',
                            'key2' => 'key2value2',
                        ]),
                    ]),
                ]),
                'expectedTest' => new Test('test name', new Configuration('', ''), [
                    'step name' => (new Step(
                        [
                            new InputAction(
                                'set $elements.action_selector to $data.key1',
                                $namedActionSelectorIdentifier,
                                new ObjectValue(ObjectValueType::DATA_PARAMETER, '$data.key1', 'key1'),
                                '$elements.action_selector to $data.key1'
                            )
                        ],
                        [
                            new ComparisonAssertion(
                                '$elements.assertion_selector is $data.key2',
                                new ExaminedValue(new DomIdentifierValue($namedAssertionSelectorIdentifier)),
                                AssertionComparison::IS,
                                new ExpectedValue(
                                    new ObjectValue(ObjectValueType::DATA_PARAMETER, '$data.key2', 'key2')
                                )
                            )
                        ]
                    ))->withDataSetCollection(new DataSetCollection([
                        new DataSet('0', [
                            'key1' => 'key1value1',
                            'key2' => 'key2value1',
                        ]),
                        new DataSet('1', [
                            'key1' => 'key1value2',
                            'key2' => 'key2value2',
                        ]),
                    ])),
                ]),
            ],
        ];
    }

    /**
     * @dataProvider resolveThrowsExceptionDataProvider
     */
    public function testResolveThrowsException(
        TestInterface $test,
        PageProviderInterface $pageProvider,
        StepProviderInterface $stepProvider,
        DataSetProviderInterface $dataSetProvider,
        string $expectedException,
        string $expectedExceptionMessage,
        ExceptionContext $expectedExceptionContext
    ) {
        try {
            $this->resolver->resolve($test, $pageProvider, $stepProvider, $dataSetProvider);
        } catch (ContextAwareExceptionInterface $contextAwareException) {
            $this->assertInstanceOf($expectedException, $contextAwareException);
            $this->assertEquals($expectedExceptionMessage, $contextAwareException->getMessage());
            $this->assertEquals($expectedExceptionContext, $contextAwareException->getExceptionContext());
        }
    }

    public function resolveThrowsExceptionDataProvider(): array
    {
        return [
            'UnknownDataProviderException: test.data references a data provider that has not been defined' => [
                'test' => new Test(
                    'test name',
                    new Configuration('chrome', 'http://example.com'),
                    [
                        'step name' => new PendingImportResolutionStep(
                            new Step([], []),
                            'step_import_name',
                            'data_provider_import_name'
                        )
                    ]
                ),
                'pageProvider' => new EmptyPageProvider(),
                'stepProvider' => new StepProvider([
                    'step_import_name' => new Step([], []),
                ]),
                'dataSetProvider' => new EmptyDataSetProvider(),
                'expectedException' => UnknownDataProviderException::class,
                'expectedExceptionMessage' => 'Unknown data provider "data_provider_import_name"',
                'expectedExceptionContext' =>  new ExceptionContext([
                    ExceptionContextInterface::KEY_TEST_NAME => 'test name',
                    ExceptionContextInterface::KEY_STEP_NAME => 'step name',
                ])
            ],
            'UnknownPageException: config.url references page not defined within a collection' => [
                'test' => new Test(
                    'test name',
                    new Configuration('chrome', 'page_import_name.url'),
                    []
                ),
                'pageProvider' => new EmptyPageProvider(),
                'stepProvider' => new EmptyStepProvider(),
                'dataSetProvider' => new EmptyDataSetProvider(),
                'expectedException' => UnknownPageException::class,
                'expectedExceptionMessage' => 'Unknown page "page_import_name"',
                'expectedExceptionContext' =>  new ExceptionContext([
                    ExceptionContextInterface::KEY_TEST_NAME => 'test name',
                ])
            ],
            'UnknownPageException: assertion string references page not defined within a collection' => [
                'test' => new Test(
                    'test name',
                    new Configuration('chrome', 'http://example.com'),
                    [
                        'step name' => new Step(
                            [],
                            [
                                (AssertionFactory::createFactory())
                                    ->createFromAssertionString('page_import_name.elements.element_name exists'),
                            ]
                        )
                    ]
                ),
                'pageProvider' => new EmptyPageProvider(),
                'stepProvider' => new EmptyStepProvider(),
                'dataSetProvider' => new EmptyDataSetProvider(),
                'expectedException' => UnknownPageException::class,
                'expectedExceptionMessage' => 'Unknown page "page_import_name"',
                'expectedExceptionContext' =>  new ExceptionContext([
                    ExceptionContextInterface::KEY_TEST_NAME => 'test name',
                    ExceptionContextInterface::KEY_STEP_NAME => 'step name',
                    ExceptionContextInterface::KEY_CONTENT => 'page_import_name.elements.element_name exists',
                ])
            ],
            'UnknownPageException: action string references page not defined within a collection' => [
                'test' => new Test(
                    'test name',
                    new Configuration('chrome', 'http://example.com'),
                    [
                        'step name' => new Step(
                            [
                                (ActionFactory::createFactory())
                                    ->createFromActionString('click page_import_name.elements.element_name')
                            ],
                            []
                        )
                    ]
                ),
                'pageProvider' => new EmptyPageProvider(),
                'stepProvider' => new EmptyStepProvider(),
                'dataSetProvider' => new EmptyDataSetProvider(),
                'expectedException' => UnknownPageException::class,
                'expectedExceptionMessage' => 'Unknown page "page_import_name"',
                'expectedExceptionContext' =>  new ExceptionContext([
                    ExceptionContextInterface::KEY_TEST_NAME => 'test name',
                    ExceptionContextInterface::KEY_STEP_NAME => 'step name',
                    ExceptionContextInterface::KEY_CONTENT => 'click page_import_name.elements.element_name',
                ])
            ],
            'UnknownPageElementException: test.elements references element that does not exist within a page' => [
                'test' => new Test(
                    'test name',
                    new Configuration('chrome', 'http://example.com'),
                    [
                        'step name' => (new Step([], []))->withIdentifierCollection(new IdentifierCollection([
                            TestIdentifierFactory::createPageElementReferenceIdentifier(
                                new PageElementReference(
                                    'page_import_name.elements.non_existent',
                                    'page_import_name',
                                    'non_existent'
                                ),
                                'non_existent'
                            ),
                        ])),
                    ]
                ),
                'pageProvider' => new PageProvider([
                    'page_import_name' => new Page(
                        new Uri('http://example.com'),
                        new IdentifierCollection()
                    )
                ]),
                'stepProvider' => new EmptyStepProvider(),
                'dataSetProvider' => new EmptyDataSetProvider(),
                'expectedException' => UnknownPageElementException::class,
                'expectedExceptionMessage' => 'Unknown page element "non_existent" in page "page_import_name"',
                'expectedExceptionContext' =>  new ExceptionContext([
                    ExceptionContextInterface::KEY_TEST_NAME => 'test name',
                    ExceptionContextInterface::KEY_STEP_NAME => 'step name',
                ])
            ],
            'UnknownPageElementException: assertion string references element that does not exist within a page' => [
                'test' => new Test(
                    'test name',
                    new Configuration('chrome', 'http://example.com'),
                    [
                        'step name' => new Step(
                            [],
                            [
                                (AssertionFactory::createFactory())
                                    ->createFromAssertionString('page_import_name.elements.non_existent exists'),
                            ]
                        ),
                    ]
                ),
                'pageProvider' => new PageProvider([
                    'page_import_name' => new Page(
                        new Uri('http://example.com'),
                        new IdentifierCollection()
                    )
                ]),
                'stepProvider' => new EmptyStepProvider(),
                'dataSetProvider' => new EmptyDataSetProvider(),
                'expectedException' => UnknownPageElementException::class,
                'expectedExceptionMessage' => 'Unknown page element "non_existent" in page "page_import_name"',
                'expectedExceptionContext' =>  new ExceptionContext([
                    ExceptionContextInterface::KEY_TEST_NAME => 'test name',
                    ExceptionContextInterface::KEY_STEP_NAME => 'step name',
                    ExceptionContextInterface::KEY_CONTENT => 'page_import_name.elements.non_existent exists',
                ])
            ],
            'UnknownPageElementException: action string references element that does not exist within a page' => [
                'test' => new Test(
                    'test name',
                    new Configuration('chrome', 'http://example.com'),
                    [
                        'step name' => new Step(
                            [
                                (ActionFactory::createFactory())
                                    ->createFromActionString('click page_import_name.elements.non_existent')
                            ],
                            []
                        ),
                    ]
                ),
                'pageProvider' => new PageProvider([
                    'page_import_name' => new Page(
                        new Uri('http://example.com'),
                        new IdentifierCollection()
                    )
                ]),
                'stepProvider' => new EmptyStepProvider(),
                'dataSetProvider' => new EmptyDataSetProvider(),
                'expectedException' => UnknownPageElementException::class,
                'expectedExceptionMessage' => 'Unknown page element "non_existent" in page "page_import_name"',
                'expectedExceptionContext' =>  new ExceptionContext([
                    ExceptionContextInterface::KEY_TEST_NAME => 'test name',
                    ExceptionContextInterface::KEY_STEP_NAME => 'step name',
                    ExceptionContextInterface::KEY_CONTENT => 'click page_import_name.elements.non_existent',
                ])
            ],
            'UnknownStepException: step.use references step not defined within a collection' => [
                'test' => new Test(
                    'test name',
                    new Configuration('chrome', 'http://example.com'),
                    [
                        'step name' => new PendingImportResolutionStep(
                            new Step([], []),
                            'step_import_name',
                            ''
                        ),
                    ]
                ),
                'pageProvider' => new EmptyPageProvider(),
                'stepProvider' => new EmptyStepProvider(),
                'dataSetProvider' => new EmptyDataSetProvider(),
                'expectedException' => UnknownStepException::class,
                'expectedExceptionMessage' => 'Unknown step "step_import_name"',
                'expectedExceptionContext' =>  new ExceptionContext([
                    ExceptionContextInterface::KEY_TEST_NAME => 'test name',
                    ExceptionContextInterface::KEY_STEP_NAME => 'step name',
                ])
            ],
            'UnknownElementException: action element parameter references unknown step element' => [
                'test' => new Test(
                    'test name',
                    new Configuration('chrome', 'http://example.com'),
                    [
                        'step name' => new Step(
                            [
                                (ActionFactory::createFactory())
                                    ->createFromActionString('click $elements.element_name')
                            ],
                            []
                        ),
                    ]
                ),
                'pageProvider' => new EmptyPageProvider(),
                'stepProvider' => new EmptyStepProvider(),
                'dataSetProvider' => new EmptyDataSetProvider(),
                'expectedException' => UnknownElementException::class,
                'expectedExceptionMessage' => 'Unknown element "element_name"',
                'expectedExceptionContext' =>  new ExceptionContext([
                    ExceptionContextInterface::KEY_TEST_NAME => 'test name',
                    ExceptionContextInterface::KEY_STEP_NAME => 'step name',
                    ExceptionContextInterface::KEY_CONTENT => 'click $elements.element_name',
                ])
            ],
            'UnknownElementException: assertion element parameter references unknown step element' => [
                'test' => new Test(
                    'test name',
                    new Configuration('chrome', 'http://example.com'),
                    [
                        'step name' => new Step(
                            [],
                            [
                                (AssertionFactory::createFactory())
                                    ->createFromAssertionString('$elements.element_name exists'),
                            ]
                        ),
                    ]
                ),
                'pageProvider' => new EmptyPageProvider(),
                'stepProvider' => new EmptyStepProvider(),
                'dataSetProvider' => new EmptyDataSetProvider(),
                'expectedException' => UnknownElementException::class,
                'expectedExceptionMessage' => 'Unknown element "element_name"',
                'expectedExceptionContext' =>  new ExceptionContext([
                    ExceptionContextInterface::KEY_TEST_NAME => 'test name',
                    ExceptionContextInterface::KEY_STEP_NAME => 'step name',
                    ExceptionContextInterface::KEY_CONTENT => '$elements.element_name exists',
                ])
            ],
        ];
    }
}
