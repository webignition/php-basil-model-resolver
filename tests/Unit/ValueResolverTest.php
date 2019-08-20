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
use webignition\BasilModel\Value\AttributeValue;
use webignition\BasilModel\Value\ElementValue;
use webignition\BasilModel\Value\EnvironmentValue;
use webignition\BasilModel\Value\LiteralValue;
use webignition\BasilModel\Value\ObjectNames;
use webignition\BasilModel\Value\ObjectValue;
use webignition\BasilModel\Value\ValueInterface;
use webignition\BasilModel\Value\ValueTypes;
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
        ValueTypes::ATTRIBUTE_PARAMETER;

        return [
            'literal css selector' => [
                'value' => LiteralValue::createCssSelectorValue('.selector'),
            ],
            'literal string' => [
                'value' => LiteralValue::createStringValue('value'),
            ],
            'literal xpath expression' => [
                'value' => LiteralValue::createXpathExpressionValue('//h1'),
            ],
            'browser object property' => [
                'value' => new ObjectValue(
                    ValueTypes::BROWSER_OBJECT_PROPERTY,
                    '$browser.size',
                    ObjectNames::BROWSER,
                    'size'
                ),
            ],
            'data parameter' => [
                'value' => new ObjectValue(
                    ValueTypes::DATA_PARAMETER,
                    '$data.key',
                    ObjectNames::DATA,
                    'key'
                ),
            ],
            'page object property' => [
                'value' => new ObjectValue(
                    ValueTypes::PAGE_OBJECT_PROPERTY,
                    '$page.url',
                    ObjectNames::PAGE,
                    'url'
                ),
            ],
            'environment parameter' => [
                'value' => new EnvironmentValue(
                    '$env.KEY',
                    'KEY'
                ),
            ],
            'element value' => [
                'value' => new ElementValue(
                    new ElementIdentifier(
                        LiteralValue::createStringValue('.selector')
                    )
                ),
            ],
            'attribute value' => [
                'value' => new AttributeValue(
                    new AttributeIdentifier(
                        new ElementIdentifier(
                            LiteralValue::createStringValue('.selector')
                        ),
                        'attribute_name'
                    )
                ),
            ],
            'malformed attribute parameter' => [
                'value' => new ObjectValue(
                    ValueTypes::ATTRIBUTE_PARAMETER,
                    '$elements.element_attribute_name',
                    ObjectNames::ELEMENT,
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
        $namedCssSelectorIdentifier = TestIdentifierFactory::createCssElementIdentifier('.selector', 1, 'element_name');

        return [
            'page element reference' => [
                'value' => new ObjectValue(
                    ValueTypes::PAGE_ELEMENT_REFERENCE,
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
                'value' => new ObjectValue(
                    ValueTypes::ELEMENT_PARAMETER,
                    '$elements.element_name',
                    ObjectNames::ELEMENT,
                    'element_name'
                ),
                'pageProvider' => new EmptyPageProvider(),
                'identifierCollection' => new IdentifierCollection([
                    $namedCssSelectorIdentifier,
                ]),
                'expectedValue' => new ElementValue($namedCssSelectorIdentifier)
            ],
            'attribute parameter parameter' => [
                'value' => new ObjectValue(
                    ValueTypes::ATTRIBUTE_PARAMETER,
                    '$elements.element_name.attribute_name',
                    ObjectNames::ELEMENT,
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
        $value = new ObjectValue(
            ValueTypes::ELEMENT_PARAMETER,
            '$elements.element_name',
            ObjectNames::ELEMENT,
            'element_name'
        );

        $this->expectException(UnknownElementException::class);
        $this->expectExceptionMessage('Unknown element "element_name"');

        $this->resolver->resolve($value, new EmptyPageProvider(), new IdentifierCollection());
    }
}
