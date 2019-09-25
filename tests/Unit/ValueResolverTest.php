<?php
/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection PhpDocSignatureInspection */

namespace webignition\BasilModelResolver\Tests\Unit;

use Nyholm\Psr7\Uri;
use webignition\BasilModel\Identifier\DomIdentifier;
use webignition\BasilModel\Identifier\IdentifierCollection;
use webignition\BasilModel\Identifier\IdentifierCollectionInterface;
use webignition\BasilModel\Page\Page;
use webignition\BasilModel\Value\DomIdentifierReference;
use webignition\BasilModel\Value\DomIdentifierReferenceType;
use webignition\BasilModel\Value\DomIdentifierValue;
use webignition\BasilModel\Value\LiteralValue;
use webignition\BasilModel\Value\ObjectValue;
use webignition\BasilModel\Value\ObjectValueType;
use webignition\BasilModel\Value\PageElementReference;
use webignition\BasilModel\Value\ValueInterface;
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
            'literal string' => [
                'value' => new LiteralValue('value'),
            ],
            'browser object property' => [
                'value' => new ObjectValue(ObjectValueType::BROWSER_PROPERTY, '$browser.size', 'size'),
            ],
            'data parameter' => [
                'value' => new ObjectValue(ObjectValueType::DATA_PARAMETER, '$data.key', 'key'),
            ],
            'page object property' => [
                'value' => new ObjectValue(ObjectValueType::PAGE_PROPERTY, '$page.url', 'url'),
            ],
            'environment parameter' => [
                'value' => new ObjectValue(ObjectValueType::ENVIRONMENT_PARAMETER, '$env.KEY', 'KEY'),
            ],
            'element value' => [
                'value' => new DomIdentifierValue(
                    new DomIdentifier('.selector')
                ),
            ],
            'attribute value' => [
                'value' => new DomIdentifierValue(
                    (new DomIdentifier('.selector'))->withAttributeName('attribute_name')
                ),
            ],
            'malformed attribute parameter' => [
                'value' => new DomIdentifierReference(
                    DomIdentifierReferenceType::ATTRIBUTE,
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
            '.selector',
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
                'expectedValue' => new DomIdentifierValue($namedCssSelectorIdentifier)
            ],
            'element parameter' => [
                'value' => new DomIdentifierReference(
                    DomIdentifierReferenceType::ELEMENT,
                    '$elements.element_name',
                    'element_name'
                ),
                'pageProvider' => new EmptyPageProvider(),
                'identifierCollection' => new IdentifierCollection([
                    $namedCssSelectorIdentifier,
                ]),
                'expectedValue' => new DomIdentifierValue($namedCssSelectorIdentifier)
            ],
            'attribute parameter' => [
                'value' => new DomIdentifierReference(
                    DomIdentifierReferenceType::ATTRIBUTE,
                    '$elements.element_name.attribute_name',
                    'element_name.attribute_name'
                ),
                'pageProvider' => new EmptyPageProvider(),
                'identifierCollection' => new IdentifierCollection([
                    $namedCssSelectorIdentifier,
                ]),
                'expectedValue' => new DomIdentifierValue(
                    ($namedCssSelectorIdentifier)->withAttributeName('attribute_name')
                ),
            ],
        ];
    }

    public function testResolveThrowsUnknownElementException()
    {
        $value = new DomIdentifierReference(
            DomIdentifierReferenceType::ELEMENT,
            '$elements.element_name',
            'element_name'
        );

        $this->expectException(UnknownElementException::class);
        $this->expectExceptionMessage('Unknown element "element_name"');

        $this->resolver->resolve($value, new EmptyPageProvider(), new IdentifierCollection());
    }
}
