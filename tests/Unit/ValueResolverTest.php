<?php
/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection PhpDocSignatureInspection */

namespace webignition\BasilModelResolver\Tests\Unit;

use Nyholm\Psr7\Uri;
use webignition\BasilModel\Identifier\AttributeIdentifier;
use webignition\BasilModel\Identifier\ElementIdentifier;
use webignition\BasilModel\Identifier\IdentifierCollection;
use webignition\BasilModel\Identifier\IdentifierCollectionInterface;
use webignition\BasilModel\Page\Page;
use webignition\BasilModel\Value\AttributeReference;
use webignition\BasilModel\Value\AttributeValue;
use webignition\BasilModel\Value\BrowserProperty;
use webignition\BasilModel\Value\CssSelector;
use webignition\BasilModel\Value\DataParameter;
use webignition\BasilModel\Value\ElementReference;
use webignition\BasilModel\Value\ElementValue;
use webignition\BasilModel\Value\EnvironmentValue;
use webignition\BasilModel\Value\LiteralValue;
use webignition\BasilModel\Value\PageElementReference;
use webignition\BasilModel\Value\PageProperty;
use webignition\BasilModel\Value\ValueInterface;
use webignition\BasilModel\Value\XpathExpression;
use webignition\BasilModelProvider\Page\EmptyPageProvider;
use webignition\BasilModelProvider\Page\PageProvider;
use webignition\BasilModelProvider\Page\PageProviderInterface;
use webignition\BasilModelResolver\UnknownElementException;
use webignition\BasilModelResolver\ValueResolver;
use webignition\BasilTestIdentifierFactory\TestIdentifierFactory;

class ValueResolverTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var ValueResolver
     */
    private $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = ValueResolver::createResolver();
    }

    /**
     * @dataProvider resolveNoChangesDataProvider
     */
    public function testResolveNoChanges(ValueInterface $value)
    {
        $this->assertSame(
            $value,
            $this->resolver->resolve($value, new EmptyPageProvider(), new IdentifierCollection())
        );
    }

    public function resolveNoChangesDataProvider(): array
    {
        return [
            'literal css selector' => [
                'value' => new CssSelector('.selector'),
            ],
            'literal string' => [
                'value' => new LiteralValue('value'),
            ],
            'literal xpath expression' => [
                'value' => new XpathExpression('//h1'),
            ],
            'browser object property' => [
                'value' => new BrowserProperty('$browser.size', 'size'),
            ],
            'data parameter' => [
                'value' => new DataParameter('$data.key', 'key'),
            ],
            'page object property' => [
                'value' => new PageProperty('$page.url', 'url'),
            ],
            'environment parameter' => [
                'value' => new EnvironmentValue('$env.KEY', 'KEY'),
            ],
            'element value' => [
                'value' => new ElementValue(
                    new ElementIdentifier(
                        new CssSelector('.selector')
                    )
                ),
            ],
            'attribute value' => [
                'value' => new AttributeValue(
                    new AttributeIdentifier(
                        new ElementIdentifier(
                            new CssSelector('.selector')
                        ),
                        'attribute_name'
                    )
                ),
            ],
            'malformed attribute parameter' => [
                'value' => new AttributeReference(
                    '$elements.element_attribute_name',
                    'element_attribute_name'
                )
            ],
        ];
    }

    /**
     * @dataProvider resolveCreatesNewValueDataProvider
     */
    public function testResolveCreatesNewValue(
        ValueInterface $value,
        PageProviderInterface $pageProvider,
        IdentifierCollectionInterface $identifierCollection,
        ValueInterface $expectedValue
    ) {
        $resolvedValue = $this->resolver->resolve($value, $pageProvider, $identifierCollection);

        $this->assertNotSame($value, $resolvedValue);
        $this->assertEquals($expectedValue, $resolvedValue);
    }

    public function resolveCreatesNewValueDataProvider(): array
    {
        $namedCssSelectorIdentifier = TestIdentifierFactory::createElementIdentifier(
            new CssSelector('.selector'),
            1,
            'element_name'
        );

        return [
            'page element reference' => [
                'value' => new PageElementReference(
                    'page_import_name.elements.element_name',
                    'page_import_name',
                    'element_name'
                ),
                'pageProvider' => new PageProvider([
                    'page_import_name' => new Page(
                        new Uri('http://example.com/'),
                        new IdentifierCollection([
                            $namedCssSelectorIdentifier,
                        ])
                    )
                ]),
                'identifierCollection' => new IdentifierCollection(),
                'expectedValue' => new ElementValue($namedCssSelectorIdentifier)
            ],
            'element parameter' => [
                'value' => new ElementReference('$elements.element_name', 'element_name'),
                'pageProvider' => new EmptyPageProvider(),
                'identifierCollection' => new IdentifierCollection([
                    $namedCssSelectorIdentifier,
                ]),
                'expectedValue' => new ElementValue($namedCssSelectorIdentifier)
            ],
            'attribute parameter parameter' => [
                'value' => new AttributeReference(
                    '$elements.element_name.attribute_name',
                    'element_name.attribute_name'
                ),
                'pageProvider' => new EmptyPageProvider(),
                'identifierCollection' => new IdentifierCollection([
                    $namedCssSelectorIdentifier,
                ]),
                'expectedValue' => new AttributeValue(
                    new AttributeIdentifier(
                        $namedCssSelectorIdentifier,
                        'attribute_name'
                    )
                ),
            ],
        ];
    }

    public function testResolveThrowsUnknownElementException()
    {
        $value = new ElementReference('$elements.element_name', 'element_name');

        $this->expectException(UnknownElementException::class);
        $this->expectExceptionMessage('Unknown element "element_name"');

        $this->resolver->resolve($value, new EmptyPageProvider(), new IdentifierCollection());
    }
}
