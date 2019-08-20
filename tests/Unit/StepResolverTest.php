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
use webignition\BasilModel\Assertion\Assertion;
use webignition\BasilModel\Assertion\AssertionComparisons;
use webignition\BasilModel\Identifier\AttributeIdentifier;
use webignition\BasilModel\Identifier\ElementIdentifier;
use webignition\BasilModel\Identifier\Identifier;
use webignition\BasilModel\Identifier\IdentifierCollection;
use webignition\BasilModel\Identifier\IdentifierTypes;
use webignition\BasilModel\Page\Page;
use webignition\BasilModel\Step\PendingImportResolutionStep;
use webignition\BasilModel\Step\Step;
use webignition\BasilModel\Step\StepInterface;
use webignition\BasilModel\Value\AttributeValue;
use webignition\BasilModel\Value\ElementValue;
use webignition\BasilModel\Value\LiteralValue;
use webignition\BasilModel\Value\ObjectNames;
use webignition\BasilModel\Value\ObjectValue;
use webignition\BasilModel\Value\ValueTypes;
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

        $namedCssElementIdentifier = TestIdentifierFactory::createCssElementIdentifier('.selector', 1, 'element_name');

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
                TestIdentifierFactory::createCssElementIdentifier('.selector'),
                new AttributeValue(
                    new AttributeIdentifier($namedCssElementIdentifier, 'attribute_name')
                ),
                '".selector" to $elements.element_name.attribute_name'
            ),
        ];

        $resolvedAssertions = [
            new Assertion(
                'page_import_name.elements.element_name exists',
                new ElementValue($namedCssElementIdentifier),
                AssertionComparisons::EXISTS
            ),
            new Assertion(
                'page_import_name.elements.element_name is page_import_name.elements.element_name',
                new ElementValue($namedCssElementIdentifier),
                AssertionComparisons::IS,
                new ElementValue($namedCssElementIdentifier)
            ),
            new Assertion(
                '$elements.element_name exists',
                new ElementValue($namedCssElementIdentifier),
                AssertionComparisons::EXISTS
            ),
            new Assertion(
                '$elements.element_name.attribute_name exists',
                new AttributeValue(
                    new AttributeIdentifier($namedCssElementIdentifier, 'attribute_name')
                ),
                AssertionComparisons::EXISTS
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

        return [
            'no resolvable actions or assertions: no actions, no assertions' => [
                'step' => new Step([], []),
                'pageProvider' => new EmptyPageProvider(),
                'expectedStep' => new Step([], []),
            ],
            'no resolvable actions or assertions: non-resolvable actions, non-resolvable assertions' => [
                'step' => new Step(
                    [
                        $nonResolvableAction
                    ],
                    [
                        $nonResolvableAssertion
                    ]
                ),
                'pageProvider' => new EmptyPageProvider(),
                'expectedStep' => new Step(
                    [
                        $nonResolvableAction
                    ],
                    [
                        $nonResolvableAssertion
                    ]
                ),
            ],
        ];
    }

    public function resolveResolvableActionsAndAssertionsDataProvider(): array
    {
        $actionFactory = ActionFactory::createFactory();
        $assertionFactory = AssertionFactory::createFactory();

        $namedCssElementIdentifier = TestIdentifierFactory::createCssElementIdentifier('.selector', 1, 'element_name');

        return [
            'resolvable page element reference in action identifier' => [
                'step' => new Step([
                    $actionFactory->createFromActionString('set page_import_name.elements.element_name to "value"'),
                ], []),
                'pageProvider' => new PageProvider([
                    'page_import_name' => new Page(
                        new Uri('http://example.com/'),
                        new IdentifierCollection([
                            $namedCssElementIdentifier,
                        ])
                    )
                ]),
                'expectedStep' => new Step([
                    new InputAction(
                        'set page_import_name.elements.element_name to "value"',
                        $namedCssElementIdentifier,
                        LiteralValue::createStringValue('value'),
                        'page_import_name.elements.element_name to "value"'
                    )
                ], []),
            ],
            'resolvable page element reference in action value' => [
                'step' => new Step([
                    $actionFactory->createFromActionString(
                        'set ".identifier-selector" to page_import_name.elements.element_name'
                    ),
                ], []),
                'pageProvider' => new PageProvider([
                    'page_import_name' => new Page(
                        new Uri('http://example.com/'),
                        new IdentifierCollection([
                            TestIdentifierFactory::createCssElementIdentifier('.value-selector', 1, 'element_name'),
                        ])
                    )
                ]),
                'expectedStep' => new Step([
                    new InputAction(
                        'set ".identifier-selector" to page_import_name.elements.element_name',
                        TestIdentifierFactory::createCssElementIdentifier('.identifier-selector'),
                        new ElementValue(
                            TestIdentifierFactory::createCssElementIdentifier('.value-selector', 1, 'element_name')
                        ),
                        '".identifier-selector" to page_import_name.elements.element_name'
                    )
                ], []),
            ],
            'resolvable page element reference in assertion examined value' => [
                'step' => new Step([], [
                    $assertionFactory->createFromAssertionString('page_import_name.elements.element_name exists'),
                ]),
                'pageProvider' => new PageProvider([
                    'page_import_name' => new Page(
                        new Uri('http://example.com/'),
                        new IdentifierCollection([
                            $namedCssElementIdentifier,
                        ])
                    )
                ]),
                'expectedStep' => new Step([], [
                    new Assertion(
                        'page_import_name.elements.element_name exists',
                        new ElementValue($namedCssElementIdentifier),
                        AssertionComparisons::EXISTS
                    ),
                ]),
            ],
            'resolvable page element reference in assertion expected value' => [
                'step' => new Step([], [
                    $assertionFactory->createFromAssertionString(
                        '".examined-selector" is page_import_name.elements.element_name '
                    ),
                ]),
                'pageProvider' => new PageProvider([
                    'page_import_name' => new Page(
                        new Uri('http://example.com/'),
                        new IdentifierCollection([
                            TestIdentifierFactory::createCssElementIdentifier('.expected-selector', 1, 'element_name'),
                        ])
                    )
                ]),
                'expectedStep' => new Step([], [
                    new Assertion(
                        '".examined-selector" is page_import_name.elements.element_name',
                        new ElementValue(
                            new ElementIdentifier(
                                LiteralValue::createCssSelectorValue('.examined-selector')
                            )
                        ),
                        AssertionComparisons::IS,
                        new ElementValue(
                            TestIdentifierFactory::createCssElementIdentifier('.expected-selector', 1, 'element_name')
                        )
                    ),
                ]),
            ],
            'resolvable element parameter in action identifier' => [
                'step' => (new Step([
                    $actionFactory->createFromActionString('set $elements.element_name to "value"'),
                ], []))->withIdentifierCollection(new IdentifierCollection([
                    $namedCssElementIdentifier,
                ])),
                'pageProvider' => new EmptyPageProvider(),
                'expectedStep' => (new Step([
                    new InputAction(
                        'set $elements.element_name to "value"',
                        $namedCssElementIdentifier,
                        LiteralValue::createStringValue('value'),
                        '$elements.element_name to "value"'
                    )
                ], []))->withIdentifierCollection(new IdentifierCollection([
                    $namedCssElementIdentifier,
                ])),
            ],
            'resolvable element parameter in action value' => [
                'step' => (new Step([
                    $actionFactory->createFromActionString('set ".selector" to $elements.element_name'),
                ], []))->withIdentifierCollection(new IdentifierCollection([
                    TestIdentifierFactory::createCssElementIdentifier('.value-selector', 1, 'element_name'),
                ])),
                'pageProvider' => new EmptyPageProvider(),
                'expectedStep' => (new Step([
                    new InputAction(
                        'set ".selector" to $elements.element_name',
                        TestIdentifierFactory::createCssElementIdentifier('.selector'),
                        new ElementValue(
                            TestIdentifierFactory::createCssElementIdentifier('.value-selector', 1, 'element_name')
                        ),
                        '".selector" to $elements.element_name'
                    )
                ], []))->withIdentifierCollection(new IdentifierCollection([
                    TestIdentifierFactory::createCssElementIdentifier('.value-selector', 1, 'element_name'),
                ])),
            ],
            'resolvable attribute parameter in action value' => [
                'step' => (new Step([
                    $actionFactory->createFromActionString('set ".selector" to $elements.element_name.attribute_name'),
                ], []))->withIdentifierCollection(new IdentifierCollection([
                    TestIdentifierFactory::createCssElementIdentifier('.value-selector', 1, 'element_name'),
                ])),
                'pageProvider' => new EmptyPageProvider(),
                'expectedStep' => (new Step([
                    new InputAction(
                        'set ".selector" to $elements.element_name.attribute_name',
                        TestIdentifierFactory::createCssElementIdentifier('.selector'),
                        new AttributeValue(
                            new AttributeIdentifier(
                                TestIdentifierFactory::createCssElementIdentifier('.value-selector', 1, 'element_name'),
                                'attribute_name'
                            )
                        ),
                        '".selector" to $elements.element_name.attribute_name'
                    )
                ], []))->withIdentifierCollection(new IdentifierCollection([
                    TestIdentifierFactory::createCssElementIdentifier('.value-selector', 1, 'element_name'),
                ])),
            ],
            'resolvable element parameter reference in assertion examined value' => [
                'step' => (new Step([], [
                    $assertionFactory->createFromAssertionString('$elements.element_name exists'),
                ]))->withIdentifierCollection(new IdentifierCollection([
                    $namedCssElementIdentifier,
                ])),
                'pageProvider' => new EmptyPageProvider(),
                'expectedStep' => (new Step([], [
                    new Assertion(
                        '$elements.element_name exists',
                        new ElementValue($namedCssElementIdentifier),
                        AssertionComparisons::EXISTS
                    ),
                ]))->withIdentifierCollection(new IdentifierCollection([
                    $namedCssElementIdentifier,
                ])),
            ],
            'resolvable element parameter reference in assertion expected value' => [
                'step' => (new Step([], [
                    $assertionFactory->createFromAssertionString('".examined-selector" is $elements.element_name'),
                ]))->withIdentifierCollection(new IdentifierCollection([
                    TestIdentifierFactory::createCssElementIdentifier('.expected-selector', 1, 'element_name'),
                ])),
                'pageProvider' => new EmptyPageProvider(),
                'expectedStep' => (new Step([], [
                    new Assertion(
                        '".examined-selector" is $elements.element_name',
                        new ElementValue(
                            new ElementIdentifier(
                                LiteralValue::createCssSelectorValue('.examined-selector')
                            )
                        ),
                        AssertionComparisons::IS,
                        new ElementValue(
                            TestIdentifierFactory::createCssElementIdentifier('.expected-selector', 1, 'element_name')
                        )
                    ),
                ]))->withIdentifierCollection(new IdentifierCollection([
                    TestIdentifierFactory::createCssElementIdentifier('.expected-selector', 1, 'element_name'),
                ])),
            ],
            'resolvable attribute parameter reference in assertion examined value' => [
                'step' => (new Step([], [
                    $assertionFactory->createFromAssertionString('$elements.element_name.attribute_name exists'),
                ]))->withIdentifierCollection(new IdentifierCollection([
                    $namedCssElementIdentifier,
                ])),
                'pageProvider' => new EmptyPageProvider(),
                'expectedStep' => (new Step([], [
                    new Assertion(
                        '$elements.element_name.attribute_name exists',
                        new AttributeValue(
                            new AttributeIdentifier(
                                $namedCssElementIdentifier,
                                'attribute_name'
                            )
                        ),
                        AssertionComparisons::EXISTS
                    ),
                ]))->withIdentifierCollection(new IdentifierCollection([
                    $namedCssElementIdentifier,
                ])),
            ],
            'resolvable attribute parameter reference in assertion expected value' => [
                'step' => (new Step([], [
                    $assertionFactory->createFromAssertionString(
                        '".examined-selector" is $elements.element_name.attribute_name'
                    ),
                ]))->withIdentifierCollection(new IdentifierCollection([
                    TestIdentifierFactory::createCssElementIdentifier('.expected-selector', 1, 'element_name'),
                ])),
                'pageProvider' => new EmptyPageProvider(),
                'expectedStep' => (new Step([], [
                    new Assertion(
                        '".examined-selector" is $elements.element_name.attribute_name',
                        new ElementValue(
                            new ElementIdentifier(
                                LiteralValue::createCssSelectorValue('.examined-selector')
                            )
                        ),
                        AssertionComparisons::IS,
                        new AttributeValue(
                            new AttributeIdentifier(
                                TestIdentifierFactory::createCssElementIdentifier(
                                    '.expected-selector',
                                    1,
                                    'element_name'
                                ),
                                'attribute_name'
                            )
                        )
                    ),
                ]))->withIdentifierCollection(new IdentifierCollection([
                    TestIdentifierFactory::createCssElementIdentifier('.expected-selector', 1, 'element_name'),
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
                    TestIdentifierFactory::createCssElementIdentifier('.selector'),
                ])),
                'pageProvider' => new EmptyPageProvider(),
                'expectedStep' => (new Step([], []))->withIdentifierCollection(new IdentifierCollection([
                    TestIdentifierFactory::createCssElementIdentifier('.selector'),
                ]))
            ],
        ];
    }

    public function resolveResolvableIdentifierCollectionDataProvider(): array
    {
        $actionFactory = ActionFactory::createFactory();
        $assertionFactory = AssertionFactory::createFactory();

        $unresolvedElementIdentifier = TestIdentifierFactory::createPageElementReferenceIdentifier(
            new ObjectValue(
                ValueTypes::PAGE_ELEMENT_REFERENCE,
                'page_import_name.elements.element_name',
                'page_import_name',
                'element_name'
            ),
            'element_name'
        );

        $resolvedElementIdentifier = TestIdentifierFactory::createCssElementIdentifier('.selector', 1, 'element_name');

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
            new Assertion(
                'page_import_name.elements.element_name exists',
                new ElementValue(
                    $resolvedElementIdentifier
                ),
                AssertionComparisons::EXISTS
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
                        new Identifier(
                            IdentifierTypes::PAGE_ELEMENT_REFERENCE,
                            new ObjectValue(
                                ValueTypes::PAGE_ELEMENT_REFERENCE,
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
                    new Assertion(
                        'page_import_name.elements.element_name exists',
                        new ObjectValue(
                            ValueTypes::PAGE_ELEMENT_REFERENCE,
                            'page_import_name.elements.element_name',
                            'page_import_name',
                            'element_name'
                        ),
                        AssertionComparisons::EXISTS
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
                        new Identifier(
                            IdentifierTypes::PAGE_ELEMENT_REFERENCE,
                            new ObjectValue(
                                ValueTypes::PAGE_ELEMENT_REFERENCE,
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
                    new Assertion(
                        'page_import_name.elements.element_name exists',
                        new ObjectValue(
                            ValueTypes::PAGE_ELEMENT_REFERENCE,
                            'page_import_name.elements.element_name',
                            'page_import_name',
                            'element_name'
                        ),
                        AssertionComparisons::EXISTS
                    )
                ]),
                'pageProvider' => new EmptyPageProvider(),
                'expectedException' => UnknownPageException::class,
                'expectedExceptionMessage' => 'Unknown page "page_import_name"',
                'expectedExceptionContext' => new ExceptionContext([
                    ExceptionContextInterface::KEY_CONTENT => 'page_import_name.elements.element_name exists',
                ]),
            ],
            'UnknownElementException: action has element parameter reference, element missing' => [
                'step' => new Step([
                    new InteractionAction(
                        'click $elements.element_name',
                        ActionTypes::CLICK,
                        new Identifier(
                            IdentifierTypes::ELEMENT_PARAMETER,
                            new ObjectValue(
                                ValueTypes::ELEMENT_PARAMETER,
                                '$elements.element_name',
                                ObjectNames::ELEMENT,
                                'element_name'
                            )
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
                    new Assertion(
                        '$elements.element_name exists',
                        new ObjectValue(
                            ValueTypes::ELEMENT_PARAMETER,
                            '$elements.element_name',
                            ObjectNames::ELEMENT,
                            'element_name'
                        ),
                        AssertionComparisons::EXISTS
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
