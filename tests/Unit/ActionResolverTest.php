<?php
/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection PhpDocSignatureInspection */

namespace webignition\BasilModelResolver\Tests\Unit;

use Nyholm\Psr7\Uri;
use webignition\BasilModel\Action\ActionInterface;
use webignition\BasilModel\Action\ActionTypes;
use webignition\BasilModel\Action\InputAction;
use webignition\BasilModel\Action\InteractionAction;
use webignition\BasilModel\Identifier\AttributeIdentifier;
use webignition\BasilModel\Identifier\ElementIdentifier;
use webignition\BasilModel\Identifier\IdentifierCollection;
use webignition\BasilModel\Identifier\IdentifierCollectionInterface;
use webignition\BasilModel\Page\Page;
use webignition\BasilModel\Value\AttributeValue;
use webignition\BasilModel\Value\ElementExpression;
use webignition\BasilModel\Value\ElementExpressionType;
use webignition\BasilModel\Value\ElementValue;
use webignition\BasilModel\Value\LiteralValue;
use webignition\BasilModelFactory\Action\ActionFactory;
use webignition\BasilModelProvider\Page\EmptyPageProvider;
use webignition\BasilModelProvider\Page\PageProvider;
use webignition\BasilModelProvider\Page\PageProviderInterface;
use webignition\BasilModelResolver\ActionResolver;
use webignition\BasilTestIdentifierFactory\TestIdentifierFactory;

class ActionResolverTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var ActionResolver
     */
    private $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = ActionResolver::createResolver();
    }

    /**
     * @dataProvider resolveLeavesActionUnchangedDataProvider
     */
    public function testResolveLeavesActionUnchanged(ActionInterface $action)
    {
        $this->assertEquals(
            $action,
            $this->resolver->resolve($action, new EmptyPageProvider(), new IdentifierCollection())
        );
    }

    public function resolveLeavesActionUnchangedDataProvider(): array
    {
        $actionFactory = ActionFactory::createFactory();

        return [
            'wait action' => [
                'action' => $actionFactory->createFromActionString('wait 30'),
            ],
            'input action with css selector' => [
                'action' => $actionFactory->createFromActionString('set ".selector" to "value"'),
            ],
            'input action with xpath expression' => [
                'action' => $actionFactory->createFromActionString('set "//foo" to "value"'),
            ],
            'input action with environment parameter value' => [
                'action' => $actionFactory->createFromActionString('set ".selector" to $env.KEY'),
            ],
            'interaction action with css selector' => [
                'action' => $actionFactory->createFromActionString('click ".selector"'),
            ],
            'interaction action with xpath expression' => [
                'action' => $actionFactory->createFromActionString('click "/foo"'),
            ],
        ];
    }

    /**
     * @dataProvider resolvePageElementReferencesCreatesNewActionDataProvider
     * @dataProvider resolveElementParametersCreatesNewActionDataProvider
     * @dataProvider resolveAttributeParametersCreatesNewActionDataProvider
     */
    public function testResolveCreatesNewAction(
        ActionInterface $action,
        PageProviderInterface $pageProvider,
        IdentifierCollectionInterface $identifierCollection,
        ActionInterface $expectedAction
    ) {
        $resolvedIdentifierContainer = $this->resolver->resolve($action, $pageProvider, $identifierCollection);

        $this->assertNotSame($action, $resolvedIdentifierContainer);
        $this->assertEquals($expectedAction, $resolvedIdentifierContainer);
    }

    public function resolvePageElementReferencesCreatesNewActionDataProvider(): array
    {
        $actionFactory = ActionFactory::createFactory();
        $namedCssElementIdentifier = TestIdentifierFactory::createElementIdentifier(
            new ElementExpression('.selector', ElementExpressionType::CSS_SELECTOR),
            1,
            'element_name'
        );

        return [
            'input action with page element reference identifier' => [
                'action' => $actionFactory->createFromActionString(
                    'set page_import_name.elements.element_name to "value"'
                ),
                'pageProvider' => new PageProvider([
                    'page_import_name' => new Page(
                        new Uri('http://example.com/'),
                        new IdentifierCollection([
                            $namedCssElementIdentifier
                        ])
                    )
                ]),
                'identifierCollection' => new IdentifierCollection(),
                'expectedAction' => new InputAction(
                    'set page_import_name.elements.element_name to "value"',
                    $namedCssElementIdentifier,
                    new LiteralValue('value'),
                    'page_import_name.elements.element_name to "value"'
                ),
            ],
            'input action with page element reference value' => [
                'action' => $actionFactory->createFromActionString(
                    'set ".selector" to page_import_name.elements.element_name'
                ),
                'pageProvider' => new PageProvider([
                    'page_import_name' => new Page(
                        new Uri('http://example.com/'),
                        new IdentifierCollection([
                            $namedCssElementIdentifier
                        ])
                    )
                ]),
                'identifierCollection' => new IdentifierCollection(),
                'expectedAction' => new InputAction(
                    'set ".selector" to page_import_name.elements.element_name',
                    new ElementIdentifier(
                        new ElementExpression('.selector', ElementExpressionType::CSS_SELECTOR)
                    ),
                    new ElementValue($namedCssElementIdentifier),
                    '".selector" to page_import_name.elements.element_name'
                ),
            ],
            'interaction action with page element reference identifier' => [
                'action' => $actionFactory->createFromActionString(
                    'click page_import_name.elements.element_name'
                ),
                'pageProvider' => new PageProvider([
                    'page_import_name' => new Page(
                        new Uri('http://example.com/'),
                        new IdentifierCollection([
                            $namedCssElementIdentifier
                        ])
                    )
                ]),
                'identifierCollection' => new IdentifierCollection(),
                'expectedAction' => new InteractionAction(
                    'click page_import_name.elements.element_name',
                    ActionTypes::CLICK,
                    $namedCssElementIdentifier,
                    'page_import_name.elements.element_name'
                ),
            ],
        ];
    }

    public function resolveElementParametersCreatesNewActionDataProvider(): array
    {
        $actionFactory = ActionFactory::createFactory();
        $namedCssElementIdentifier = TestIdentifierFactory::createElementIdentifier(
            new ElementExpression('.selector', ElementExpressionType::CSS_SELECTOR),
            1,
            'element_name'
        );

        return [
            'input action with element parameter identifier' => [
                'action' => $actionFactory->createFromActionString('set $elements.element_name to "value"'),
                'pageProvider' => new EmptyPageProvider(),
                'identifierCollection' => new IdentifierCollection([
                    $namedCssElementIdentifier,
                ]),
                'expectedAction' => new InputAction(
                    'set $elements.element_name to "value"',
                    $namedCssElementIdentifier,
                    new LiteralValue('value'),
                    '$elements.element_name to "value"'
                ),
            ],
            'input action with element parameter value' => [
                'action' => $actionFactory->createFromActionString('set ".selector" to $elements.element_name'),
                'pageProvider' => new EmptyPageProvider(),
                'identifierCollection' => new IdentifierCollection([
                    $namedCssElementIdentifier,
                ]),
                'expectedAction' => new InputAction(
                    'set ".selector" to $elements.element_name',
                    new ElementIdentifier(
                        new ElementExpression('.selector', ElementExpressionType::CSS_SELECTOR)
                    ),
                    new ElementValue($namedCssElementIdentifier),
                    '".selector" to $elements.element_name'
                ),
            ],
            'interaction action with element parameter identifier' => [
                'action' => $actionFactory->createFromActionString('click $elements.element_name'),
                'pageProvider' => new EmptyPageProvider(),
                'identifierCollection' => new IdentifierCollection([
                    $namedCssElementIdentifier,
                ]),
                'expectedAction' => new InteractionAction(
                    'click $elements.element_name',
                    ActionTypes::CLICK,
                    $namedCssElementIdentifier,
                    '$elements.element_name'
                ),
            ],
        ];
    }

    public function resolveAttributeParametersCreatesNewActionDataProvider(): array
    {
        $actionFactory = ActionFactory::createFactory();
        $namedCssElementIdentifier = TestIdentifierFactory::createElementIdentifier(
            new ElementExpression('.selector', ElementExpressionType::CSS_SELECTOR),
            1,
            'element_name'
        );

        return [
            'input action with attribute parameter value' => [
                'action' => $actionFactory->createFromActionString(
                    'set ".selector" to $elements.element_name.attribute_name'
                ),
                'pageProvider' => new EmptyPageProvider(),
                'identifierCollection' => new IdentifierCollection([
                    $namedCssElementIdentifier,
                ]),
                'expectedAction' => new InputAction(
                    'set ".selector" to $elements.element_name.attribute_name',
                    new ElementIdentifier(
                        new ElementExpression('.selector', ElementExpressionType::CSS_SELECTOR)
                    ),
                    new AttributeValue(
                        new AttributeIdentifier(
                            $namedCssElementIdentifier,
                            'attribute_name'
                        )
                    ),
                    '".selector" to $elements.element_name.attribute_name'
                ),
            ],
        ];
    }
}
