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
use webignition\BasilModel\Identifier\AttributeIdentifier;
use webignition\BasilModel\Identifier\IdentifierCollection;
use webignition\BasilModel\Identifier\ReferenceIdentifier;
use webignition\BasilModel\Page\Page;
use webignition\BasilModel\Step\PendingImportResolutionStep;
use webignition\BasilModel\Step\Step;
use webignition\BasilModel\Step\StepInterface;
use webignition\BasilModel\Value\AssertionExaminedValue;
use webignition\BasilModel\Value\AssertionExpectedValue;
use webignition\BasilModel\Value\AttributeValue;
use webignition\BasilModel\Value\CssSelector;
use webignition\BasilModel\Value\ElementReference;
use webignition\BasilModel\Value\ElementValue;
use webignition\BasilModel\Value\LiteralValue;
use webignition\BasilModel\Value\PageElementReference;
use webignition\BasilModelFactory\Action\ActionFactory;
use webignition\BasilModelFactory\AssertionFactory;
use webignition\BasilModelProvider\Exception\UnknownPageException;
use webignition\BasilModelProvider\Page\EmptyPageProvider;
use webignition\BasilModelProvider\Page\PageProvider;
use webignition\BasilModelProvider\Page\PageProviderInterface;
use webignition\BasilModelResolver\StepResolver;
use webignition\BasilModelResolver\UnknownElementException;
use webignition\BasilModelResolver\UnknownPageElementException;
use webignition\BasilTestIdentifierFactory\TestIdentifierFactory;

class StepResolverTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var StepResolver
     */
    private $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = StepResolver::createResolver();
    }

    /**
     * @dataProvider resolveForPendingImportStepDataProvider
     * @dataProvider resolveNonResolvableActionsOrAssertions
     * @dataProvider resolveResolvableActionsAndAssertionsDataProvider
     * @dataProvider resolveNonResolvableIdentifierCollectionDataProvider
     * @dataProvider resolveResolvableIdentifierCollectionDataProvider
     */
    public function testResolveSuccess(
        StepInterface $step,
        PageProviderInterface $pageProvider,
        StepInterface $expectedStep
    ) {
        $resolvedStep = $this->resolver->resolve($step, $pageProvider);

        $this->assertEquals($expectedStep, $resolvedStep);
    }

    public function resolveForPendingImportStepDataProvider(): array
    {
        $actionFactory = ActionFactory::createFactory();
        $assertionFactory = AssertionFactory::createFactory();

        $nonResolvableActions = [
            $actionFactory->createFromActionString('wait 1'),
        ];

        $nonResolvableAssertions = [
            $assertionFactory->createFromAssertionString('".selector" exists'),
        ];

        $resolvableActions = [
            $actionFactory->createFromActionString('click page_import_name.elements.element_name'),
            $actionFactory->createFromActionString(
                'set page_import_name.elements.element_name to page_import_name.elements.element_name'
            ),
            $actionFactory->createFromActionString('click $elements.element_name'),
            $actionFactory->createFromActionString(
                'set $elements.element_name to $elements.element_name'
            ),
            $actionFactory->createFromActionString(
                'set ".selector" to $elements.element_name.attribute_name'
            ),
        ];

        $resolvableAssertions = [
            $assertionFactory->createFromAssertionString('page_import_name.elements.element_name exists'),
            $assertionFactory->createFromAssertionString(
                'page_import_name.elements.element_name is page_import_name.elements.element_name'
            ),
            $assertionFactory->createFromAssertionString('$elements.element_name exists'),
            $assertionFactory->createFromAssertionString('$elements.element_name.attribute_name exists'),
        ];

        $namedCssElementIdentifier = TestIdentifierFactory::createElementIdentifier(
            new CssSelector('.selector'),
            1,
            'element_name'
        );

        $resolvedActions = [
            new InteractionAction(
                'click page_import_name.elements.element_name',
                ActionTypes::CLICK,
                $namedCssElementIdentifier,
                'page_import_name.elements.element_name'
            ),
            new InputAction(
                'set page_import_name.elements.element_name to page_import_name.elements.element_name',
                $namedCssElementIdentifier,
                new ElementValue($namedCssElementIdentifier),
                'page_import_name.elements.element_name to page_import_name.elements.element_name'
            ),
            new InteractionAction(
                'click $elements.element_name',
                ActionTypes::CLICK,
                $namedCssElementIdentifier,
                '$elements.element_name'
            ),
            new InputAction(
                'set $elements.element_name to $elements.element_name',
                $namedCssElementIdentifier,
                new ElementValue($namedCssElementIdentifier),
                '$elements.element_name to $elements.element_name'
            ),
            new InputAction(
                'set ".selector" to $elements.element_name.attribute_name',
                TestIdentifierFactory::createElementIdentifier(new CssSelector('.selector')),
                new AttributeValue(
                    new AttributeIdentifier($namedCssElementIdentifier, 'attribute_name')
                ),
                '".selector" to $elements.element_name.attribute_name'
            ),
        ];

        $resolvedAssertions = [
            new ExaminationAssertion(
                'page_import_name.elements.element_name exists',
                new AssertionExaminedValue(
                    new ElementValue($namedCssElementIdentifier)
                ),
                AssertionComparison::EXISTS
            ),
            new ComparisonAssertion(
                'page_import_name.elements.element_name is page_import_name.elements.element_name',
                new AssertionExaminedValue(new ElementValue($namedCssElementIdentifier)),
                AssertionComparison::IS,
                new AssertionExpectedValue(new ElementValue($namedCssElementIdentifier))
            ),
            new ExaminationAssertion(
                '$elements.element_name exists',
                new AssertionExaminedValue(new ElementValue($namedCssElementIdentifier)),
                AssertionComparison::EXISTS
            ),
            new ExaminationAssertion(
                '$elements.element_name.attribute_name exists',
                new AssertionExaminedValue(new AttributeValue(
                    new AttributeIdentifier($namedCssElementIdentifier, 'attribute_name')
                )),
                AssertionComparison::EXISTS
            ),
        ];

        $identifierCollection = new IdentifierCollection([
            $namedCssElementIdentifier,
        ]);

        $pageProvider = new PageProvider([
            'page_import_name' => new Page(
                new Uri('http://example.com/'),
                $identifierCollection
            )
        ]);

        return [
            'pending import step: empty step not requiring import resolution' => [
                'step' => new PendingImportResolutionStep(new Step([], []), '', ''),
                'pageProvider' => new EmptyPageProvider(),
                'expectedStep' => new Step([], []),
            ],
            'pending import step: non-empty step, has non-resolvable actions and assertions' => [
                'step' => new PendingImportResolutionStep(
                    new Step($nonResolvableActions, $nonResolvableAssertions),
                    '',
                    ''
                ),
                'pageProvider' => new EmptyPageProvider(),
                'expectedStep' => new Step($nonResolvableActions, $nonResolvableAssertions),
            ],
            'pending import step: non-empty step, has resolvable actions and assertions' => [
                'step' => (new PendingImportResolutionStep(
                    new Step($resolvableActions, $resolvableAssertions),
                    '',
                    ''
                ))->withIdentifierCollection($identifierCollection),
                'pageProvider' => $pageProvider,
                'expectedStep' => (new Step($resolvedActions, $resolvedAssertions))
                    ->withIdentifierCollection(clone $identifierCollection),
            ],
            'pending import step: has step import name' => [
                'step' => new PendingImportResolutionStep(new Step([], []), 'step_import_name', ''),
                'pageProvider' => new EmptyPageProvider(),
                'expectedStep' => new PendingImportResolutionStep(new Step([], []), 'step_import_name', ''),
            ],
            'pending import step: has data provider import name' => [
                'step' => new PendingImportResolutionStep(new Step([], []), '', 'data_provider_import_name'),
                'pageProvider' => new EmptyPageProvider(),
                'expectedStep' => new PendingImportResolutionStep(new Step([], []), '', 'data_provider_import_name'),
            ],
            'no resolvable actions or assertions: no actions, no assertions' => [
                'step' => new Step([], []),
                'pageProvider' => new EmptyPageProvider(),
                'expectedStep' => new Step([], []),
            ],
            'no resolvable actions or assertions: non-resolvable actions, non-resolvable assertions' => [
                'step' => new Step($nonResolvableActions, $nonResolvableAssertions),
                'pageProvider' => new EmptyPageProvider(),
                'expectedStep' => new Step($nonResolvableActions, $nonResolvableAssertions),
            ],
        ];
    }

    public function resolveNonResolvableActionsOrAssertions(): array
    {
        $actionFactory = ActionFactory::createFactory();
        $assertionFactory = AssertionFactory::createFactory();

        $nonResolvableAction = $actionFactory->createFromActionString('wait 30');
        $nonResolvableAssertion = $assertionFactory->createFromAssertionString('".selector" exists');

        $nonResolvableStep = new Step(
            [
                $nonResolvableAction
            ],
            [
                $nonResolvableAssertion
            ]
        );

        return [
            'no resolvable actions or assertions: no actions, no assertions' => [
                'step' => new Step([], []),
                'pageProvider' => new EmptyPageProvider(),
                'expectedStep' => new Step([], []),
            ],
            'no resolvable actions or assertions: non-resolvable actions, non-resolvable assertions' => [
                'step' => $nonResolvableStep,
                'pageProvider' => new EmptyPageProvider(),
                'expectedStep' => $nonResolvableStep
            ],
        ];
    }

    public function resolveResolvableActionsAndAssertionsDataProvider(): array
    {
        $actionFactory = ActionFactory::createFactory();
        $assertionFactory = AssertionFactory::createFactory();

        $examinedIdentifier = TestIdentifierFactory::createElementIdentifier(
            new CssSelector('.examined-selector')
        );

        $namedExaminedIdentifier = TestIdentifierFactory::createElementIdentifier(
            new CssSelector('.examined-selector'),
            1,
            'examined'
        );

        $namedExpectedIdentifier = TestIdentifierFactory::createElementIdentifier(
            new CssSelector('.expected-selector'),
            1,
            'expected'
        );

        return [
            'resolvable page element reference in action identifier' => [
                'step' => new Step([
                    $actionFactory->createFromActionString('set page_import_name.elements.examined to "value"'),
                ], []),
                'pageProvider' => new PageProvider([
                    'page_import_name' => new Page(
                        new Uri('http://example.com/'),
                        new IdentifierCollection([
                            $namedExaminedIdentifier,
                        ])
                    )
                ]),
                'expectedStep' => new Step([
                    new InputAction(
                        'set page_import_name.elements.examined to "value"',
                        $namedExaminedIdentifier,
                        new LiteralValue('value'),
                        'page_import_name.elements.examined to "value"'
                    )
                ], []),
            ],
            'resolvable page element reference in action value' => [
                'step' => new Step([
                    $actionFactory->createFromActionString(
                        'set ".examined-selector" to page_import_name.elements.expected'
                    ),
                ], []),
                'pageProvider' => new PageProvider([
                    'page_import_name' => new Page(
                        new Uri('http://example.com/'),
                        new IdentifierCollection([
                            $namedExpectedIdentifier,
                        ])
                    )
                ]),
                'expectedStep' => new Step([
                    new InputAction(
                        'set ".examined-selector" to page_import_name.elements.expected',
                        $examinedIdentifier,
                        new ElementValue($namedExpectedIdentifier),
                        '".examined-selector" to page_import_name.elements.expected'
                    )
                ], []),
            ],
            'resolvable page element reference in assertion examined value' => [
                'step' => new Step([], [
                    $assertionFactory->createFromAssertionString('page_import_name.elements.examined exists'),
                ]),
                'pageProvider' => new PageProvider([
                    'page_import_name' => new Page(
                        new Uri('http://example.com/'),
                        new IdentifierCollection([
                            $namedExaminedIdentifier,
                        ])
                    )
                ]),
                'expectedStep' => new Step([], [
                    new ExaminationAssertion(
                        'page_import_name.elements.examined exists',
                        new AssertionExaminedValue(new ElementValue($namedExaminedIdentifier)),
                        AssertionComparison::EXISTS
                    ),
                ]),
            ],
            'resolvable page element reference in assertion expected value' => [
                'step' => new Step([], [
                    $assertionFactory->createFromAssertionString(
                        '".examined-selector" is page_import_name.elements.expected '
                    ),
                ]),
                'pageProvider' => new PageProvider([
                    'page_import_name' => new Page(
                        new Uri('http://example.com/'),
                        new IdentifierCollection([
                            $namedExpectedIdentifier,
                        ])
                    )
                ]),
                'expectedStep' => new Step([], [
                    new ComparisonAssertion(
                        '".examined-selector" is page_import_name.elements.expected',
                        new AssertionExaminedValue(new ElementValue($examinedIdentifier)),
                        AssertionComparison::IS,
                        new AssertionExpectedValue(new ElementValue($namedExpectedIdentifier))
                    ),
                ]),
            ],
            'resolvable element parameter in action identifier' => [
                'step' => (new Step([
                    $actionFactory->createFromActionString('set $elements.examined to "value"'),
                ], []))->withIdentifierCollection(new IdentifierCollection([
                    $namedExaminedIdentifier,
                ])),
                'pageProvider' => new EmptyPageProvider(),
                'expectedStep' => (new Step([
                    new InputAction(
                        'set $elements.examined to "value"',
                        $namedExaminedIdentifier,
                        new LiteralValue('value'),
                        '$elements.examined to "value"'
                    )
                ], []))->withIdentifierCollection(new IdentifierCollection([
                    $namedExaminedIdentifier,
                ])),
            ],
            'resolvable element parameter in action value' => [
                'step' => (new Step([
                    $actionFactory->createFromActionString('set ".examined-selector" to $elements.expected'),
                ], []))->withIdentifierCollection(new IdentifierCollection([
                    $namedExpectedIdentifier,
                ])),
                'pageProvider' => new EmptyPageProvider(),
                'expectedStep' => (new Step([
                    new InputAction(
                        'set ".examined-selector" to $elements.expected',
                        $examinedIdentifier,
                        new ElementValue($namedExpectedIdentifier),
                        '".examined-selector" to $elements.expected'
                    )
                ], []))->withIdentifierCollection(new IdentifierCollection([
                    $namedExpectedIdentifier,
                ])),
            ],
            'resolvable attribute parameter in action value' => [
                'step' => (new Step([
                    $actionFactory->createFromActionString(
                        'set ".examined-selector" to $elements.expected.attribute_name'
                    ),
                ], []))->withIdentifierCollection(new IdentifierCollection([
                    $namedExpectedIdentifier,
                ])),
                'pageProvider' => new EmptyPageProvider(),
                'expectedStep' => (new Step([
                    new InputAction(
                        'set ".examined-selector" to $elements.expected.attribute_name',
                        $examinedIdentifier,
                        new AttributeValue(
                            new AttributeIdentifier(
                                $namedExpectedIdentifier,
                                'attribute_name'
                            )
                        ),
                        '".examined-selector" to $elements.expected.attribute_name'
                    )
                ], []))->withIdentifierCollection(new IdentifierCollection([
                    $namedExpectedIdentifier,
                ])),
            ],
            'resolvable element parameter reference in assertion examined value' => [
                'step' => (new Step([], [
                    $assertionFactory->createFromAssertionString('$elements.examined exists'),
                ]))->withIdentifierCollection(new IdentifierCollection([
                    $namedExaminedIdentifier,
                ])),
                'pageProvider' => new EmptyPageProvider(),
                'expectedStep' => (new Step([], [
                    new ExaminationAssertion(
                        '$elements.examined exists',
                        new AssertionExaminedValue(new ElementValue($namedExaminedIdentifier)),
                        AssertionComparison::EXISTS
                    ),
                ]))->withIdentifierCollection(new IdentifierCollection([
                    $namedExaminedIdentifier,
                ])),
            ],
            'resolvable element parameter reference in assertion expected value' => [
                'step' => (new Step([], [
                    $assertionFactory->createFromAssertionString('".examined-selector" is $elements.expected'),
                ]))->withIdentifierCollection(new IdentifierCollection([
                    $namedExpectedIdentifier,
                ])),
                'pageProvider' => new EmptyPageProvider(),
                'expectedStep' => (new Step([], [
                    new ComparisonAssertion(
                        '".examined-selector" is $elements.expected',
                        new AssertionExaminedValue(new ElementValue($examinedIdentifier)),
                        AssertionComparison::IS,
                        new AssertionExpectedValue(new ElementValue($namedExpectedIdentifier))
                    ),
                ]))->withIdentifierCollection(new IdentifierCollection([
                    $namedExpectedIdentifier,
                ])),
            ],
            'resolvable attribute parameter reference in assertion examined value' => [
                'step' => (new Step([], [
                    $assertionFactory->createFromAssertionString('$elements.examined.attribute_name exists'),
                ]))->withIdentifierCollection(new IdentifierCollection([
                    $namedExaminedIdentifier,
                ])),
                'pageProvider' => new EmptyPageProvider(),
                'expectedStep' => (new Step([], [
                    new ExaminationAssertion(
                        '$elements.examined.attribute_name exists',
                        new AssertionExaminedValue(
                            new AttributeValue(
                                new AttributeIdentifier(
                                    $namedExaminedIdentifier,
                                    'attribute_name'
                                )
                            )
                        ),
                        AssertionComparison::EXISTS
                    ),
                ]))->withIdentifierCollection(new IdentifierCollection([
                    $namedExaminedIdentifier,
                ])),
            ],
            'resolvable attribute parameter reference in assertion expected value' => [
                'step' => (new Step([], [
                    $assertionFactory->createFromAssertionString(
                        '".examined-selector" is $elements.expected.attribute_name'
                    ),
                ]))->withIdentifierCollection(new IdentifierCollection([
                    $namedExpectedIdentifier,
                ])),
                'pageProvider' => new EmptyPageProvider(),
                'expectedStep' => (new Step([], [
                    new ComparisonAssertion(
                        '".examined-selector" is $elements.expected.attribute_name',
                        new AssertionExaminedValue(new ElementValue($examinedIdentifier)),
                        AssertionComparison::IS,
                        new AssertionExpectedValue(
                            new AttributeValue(
                                new AttributeIdentifier(
                                    $namedExpectedIdentifier,
                                    'attribute_name'
                                )
                            )
                        )
                    ),
                ]))->withIdentifierCollection(new IdentifierCollection([
                    $namedExpectedIdentifier,
                ])),
            ],
        ];
    }

    public function resolveNonResolvableIdentifierCollectionDataProvider(): array
    {
        return [
            'non-resolvable identifier collection: no element identifiers' => [
                'step' => new Step([], []),
                'pageProvider' => new EmptyPageProvider(),
                'expectedStep' => new Step([], []),
            ],
            'non-resolvable identifier collection: no resolvable element identifiers' => [
                'step' => (new Step([], []))->withIdentifierCollection(new IdentifierCollection([
                    TestIdentifierFactory::createElementIdentifier(new CssSelector('.selector')),
                ])),
                'pageProvider' => new EmptyPageProvider(),
                'expectedStep' => (new Step([], []))->withIdentifierCollection(new IdentifierCollection([
                    TestIdentifierFactory::createElementIdentifier(new CssSelector('.selector')),
                ]))
            ],
        ];
    }

    public function resolveResolvableIdentifierCollectionDataProvider(): array
    {
        $actionFactory = ActionFactory::createFactory();
        $assertionFactory = AssertionFactory::createFactory();

        $unresolvedElementIdentifier = TestIdentifierFactory::createPageElementReferenceIdentifier(
            new PageElementReference(
                'page_import_name.elements.element_name',
                'page_import_name',
                'element_name'
            ),
            'element_name'
        );

        $resolvedElementIdentifier = TestIdentifierFactory::createElementIdentifier(
            new CssSelector('.selector'),
            1,
            'element_name'
        );

        $unresolvedActions = [
            $actionFactory->createFromActionString('click page_import_name.elements.element_name'),
        ];

        $unresolvedAssertions = [
            $assertionFactory->createFromAssertionString('page_import_name.elements.element_name exists'),
        ];

        $resolvedActions = [
            new InteractionAction(
                'click page_import_name.elements.element_name',
                ActionTypes::CLICK,
                $resolvedElementIdentifier,
                'page_import_name.elements.element_name'
            ),
        ];

        $resolvedAssertions = [
            new ExaminationAssertion(
                'page_import_name.elements.element_name exists',
                new AssertionExaminedValue(
                    new ElementValue(
                        $resolvedElementIdentifier
                    )
                ),
                AssertionComparison::EXISTS
            ),
        ];

        return [
            'resolvable identifier collection: page element references, unused by actions or assertions' => [
                'step' => (new Step([], []))->withIdentifierCollection(new IdentifierCollection([
                    $unresolvedElementIdentifier,
                ])),
                'pageProvider' => new PageProvider([
                    'page_import_name' => new Page(
                        new Uri('http://example.com/'),
                        new IdentifierCollection([
                            $resolvedElementIdentifier,
                        ])
                    )
                ]),
                'expectedStep' => (new Step([], []))->withIdentifierCollection(new IdentifierCollection([
                    $resolvedElementIdentifier,
                ]))
            ],
            'resolvable identifier collection: page element references, used by actions and assertions' => [
                'step' => (new Step($unresolvedActions, $unresolvedAssertions))
                    ->withIdentifierCollection(new IdentifierCollection([
                        $unresolvedElementIdentifier,
                    ])),
                'pageProvider' => new PageProvider([
                    'page_import_name' => new Page(
                        new Uri('http://example.com/'),
                        new IdentifierCollection([
                            $resolvedElementIdentifier,
                        ])
                    )
                ]),
                'expectedStep' => (new Step($resolvedActions, $resolvedAssertions))
                    ->withIdentifierCollection(new IdentifierCollection([
                        $resolvedElementIdentifier,
                    ]))
            ],
        ];
    }

    /**
     * @dataProvider resolvePageElementReferencesThrowsExceptionDataProvider
     */
    public function testResolvePageElementReferencesThrowsException(
        StepInterface $step,
        PageProviderInterface $pageProvider,
        string $expectedException,
        string $expectedExceptionMessage,
        ExceptionContextInterface $expectedExceptionContext
    ) {
        try {
            $this->resolver->resolve($step, $pageProvider);

            $this->fail('Exception "' . $expectedException . '" not thrown');
        } catch (\Exception $exception) {
            $this->assertInstanceOf($expectedException, $exception);
            $this->assertSame($expectedExceptionMessage, $exception->getMessage());

            if ($exception instanceof ContextAwareExceptionInterface) {
                $this->assertEquals($expectedExceptionContext, $exception->getExceptionContext());
            }
        }
    }

    public function resolvePageElementReferencesThrowsExceptionDataProvider(): array
    {
        return [
            'UnknownPageElementException: action has page element reference, referenced page lacks element' => [
                'step' => new Step([
                    new InteractionAction(
                        'click page_import_name.elements.element_name',
                        ActionTypes::CLICK,
                        ReferenceIdentifier::createPageElementReferenceIdentifier(
                            new PageElementReference(
                                'page_import_name.elements.element_name',
                                'page_import_name',
                                'element_name'
                            )
                        ),
                        'page_import_name.elements.element_name'
                    )
                ], []),
                'pageProvider' => new PageProvider([
                    'page_import_name' => new Page(
                        new Uri('https://example.com'),
                        new IdentifierCollection()
                    ),
                ]),
                'expectedException' => UnknownPageElementException::class,
                'expectedExceptionMessage' => 'Unknown page element "element_name" in page "page_import_name"',
                'expectedExceptionContext' => new ExceptionContext([
                    ExceptionContextInterface::KEY_CONTENT => 'click page_import_name.elements.element_name',
                ]),
            ],
            'UnknownPageElementException: assertion has page element reference, referenced page lacks element' => [
                'step' => new Step([], [
                    new ExaminationAssertion(
                        'page_import_name.elements.element_name exists',
                        new AssertionExaminedValue(
                            new PageElementReference(
                                'page_import_name.elements.element_name',
                                'page_import_name',
                                'element_name'
                            )
                        ),
                        AssertionComparison::EXISTS
                    )
                ]),
                'pageProvider' => new PageProvider([
                    'page_import_name' => new Page(
                        new Uri('https://example.com'),
                        new IdentifierCollection()
                    ),
                ]),
                'expectedException' => UnknownPageElementException::class,
                'expectedExceptionMessage' => 'Unknown page element "element_name" in page "page_import_name"',
                'expectedExceptionContext' => new ExceptionContext([
                    ExceptionContextInterface::KEY_CONTENT => 'page_import_name.elements.element_name exists',
                ]),
            ],
            'UnknownPageException: action has page element reference, page does not exist' => [
                'step' => new Step([
                    new InteractionAction(
                        'click page_import_name.elements.element_name',
                        ActionTypes::CLICK,
                        ReferenceIdentifier::createPageElementReferenceIdentifier(
                            new PageElementReference(
                                'page_import_name.elements.element_name',
                                'page_import_name',
                                'element_name'
                            )
                        ),
                        'page_import_name.elements.element_name'
                    )
                ], []),
                'pageProvider' => new EmptyPageProvider(),
                'expectedException' => UnknownPageException::class,
                'expectedExceptionMessage' => 'Unknown page "page_import_name"',
                'expectedExceptionContext' => new ExceptionContext([
                    ExceptionContextInterface::KEY_CONTENT => 'click page_import_name.elements.element_name',
                ]),
            ],
            'UnknownPageException: assertion has page element reference, page does not exist' => [
                'step' => new Step([], [
                    new ExaminationAssertion(
                        'page_import_name.elements.element_name exists',
                        new AssertionExaminedValue(
                            new PageElementReference(
                                'page_import_name.elements.element_name',
                                'page_import_name',
                                'element_name'
                            )
                        ),
                        AssertionComparison::EXISTS
                    )
                ]),
                'pageProvider' => new EmptyPageProvider(),
                'expectedException' => UnknownPageException::class,
                'expectedExceptionMessage' => 'Unknown page "page_import_name"',
                'expectedExceptionContext' => new ExceptionContext([
                    ExceptionContextInterface::KEY_CONTENT => 'page_import_name.elements.element_name exists',
                ]),
            ],
            'UnknownElementException: action has element reference, element missing' => [
                'step' => new Step([
                    new InteractionAction(
                        'click $elements.element_name',
                        ActionTypes::CLICK,
                        ReferenceIdentifier::createElementReferenceIdentifier(
                            new ElementReference('$elements.element_name', 'element_name')
                        ),
                        '$elements.element_name'
                    )
                ], []),
                'pageProvider' => new EmptyPageProvider(),
                'expectedException' => UnknownElementException::class,
                'expectedExceptionMessage' => 'Unknown element "element_name"',
                'expectedExceptionContext' => new ExceptionContext([
                    ExceptionContextInterface::KEY_CONTENT => 'click $elements.element_name',
                ]),
            ],
            'UnknownElementException: assertion has page element reference, referenced page invalid' => [
                'step' => new Step([], [
                    new ExaminationAssertion(
                        '$elements.element_name exists',
                        new AssertionExaminedValue(
                            new ElementReference('$elements.element_name', 'element_name')
                        ),
                        AssertionComparison::EXISTS
                    )
                ]),
                'pageProvider' => new EmptyPageProvider(),
                'expectedException' => UnknownElementException::class,
                'expectedExceptionMessage' => 'Unknown element "element_name"',
                'expectedExceptionContext' => new ExceptionContext([
                    ExceptionContextInterface::KEY_CONTENT => '$elements.element_name exists',
                ]),
            ],
        ];
    }
}
