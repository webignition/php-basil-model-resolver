<?php
/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection PhpDocSignatureInspection */

namespace webignition\BasilModelResolver\Tests\Unit;

use Nyholm\Psr7\Uri;
use webignition\BasilModel\Identifier\IdentifierCollection;
use webignition\BasilModel\Identifier\IdentifierCollectionInterface;
use webignition\BasilModel\Identifier\IdentifierInterface;
use webignition\BasilModel\Identifier\ReferenceIdentifier;
use webignition\BasilModel\Page\Page;
use webignition\BasilModel\Value\AttributeReference;
use webignition\BasilModel\Value\ElementExpression;
use webignition\BasilModel\Value\ElementExpressionType;
use webignition\BasilModel\Value\ElementReference;
use webignition\BasilModel\Value\PageElementReference;
use webignition\BasilModelProvider\Page\EmptyPageProvider;
use webignition\BasilModelProvider\Page\PageProvider;
use webignition\BasilModelProvider\Page\PageProviderInterface;
use webignition\BasilModelResolver\IdentifierResolver;
use webignition\BasilModelResolver\UnknownElementException;
use webignition\BasilTestIdentifierFactory\TestIdentifierFactory;

class IdentifierResolverTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var IdentifierResolver
     */
    private $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = IdentifierResolver::createResolver();
    }

    /**
     * @dataProvider resolveNonResolvableDataProvider
     */
    public function testResolveNonResolvable(IdentifierInterface $identifier)
    {
        $resolvedIdentifier = $this->resolver->resolve(
            $identifier,
            new EmptyPageProvider(),
            new IdentifierCollection()
        );

        $this->assertSame($identifier, $resolvedIdentifier);
    }

    public function resolveNonResolvableDataProvider(): array
    {
        return [
            'wrong identifier type' => [
                'identifier' => TestIdentifierFactory::createElementIdentifier(
                    new ElementExpression('.selector', ElementExpressionType::CSS_SELECTOR)
                ),
            ],
            'wrong value type' => [
                'identifier' => ReferenceIdentifier::createPageElementReferenceIdentifier(
                    new AttributeReference(
                        '$elements.element_name.attribute_name',
                        'element_name.attribute_name'
                    )
                ),
            ],
        ];
    }

    /**
     * @dataProvider resolveDataProvider
     */
    public function testResolveIsResolved(
        IdentifierInterface $identifier,
        PageProviderInterface $pageProvider,
        IdentifierCollectionInterface $identifierCollection,
        IdentifierInterface $expectedIdentifier
    ) {
        $resolvedIdentifier = $this->resolver->resolve($identifier, $pageProvider, $identifierCollection);

        $this->assertEquals($expectedIdentifier, $resolvedIdentifier);
    }

    public function resolveDataProvider(): array
    {
        $cssElementIdentifier = TestIdentifierFactory::createElementIdentifier(
            new ElementExpression('.selector', ElementExpressionType::CSS_SELECTOR)
        );

        $cssElementIdentifierWithName = $cssElementIdentifier->withName('element_name');

        return [
            'resolvable page element reference' => [
                'identifier' => ReferenceIdentifier::createPageElementReferenceIdentifier(
                    new PageElementReference(
                        'page_import_name.elements.element_name',
                        'page_import_name',
                        'element_name'
                    )
                ),
                'pageProvider' => new PageProvider([
                    'page_import_name' => new Page(
                        new Uri('http://example.com/'),
                        new IdentifierCollection([
                            $cssElementIdentifierWithName,
                        ])
                    )
                ]),
                'identifierCollection' => new IdentifierCollection(),
                'expectedIdentifier' => $cssElementIdentifierWithName,
            ],
            'element parameter' => [
                'identifier' => ReferenceIdentifier::createElementReferenceIdentifier(
                    new ElementReference('$elements.element_name', 'element_name')
                ),
                'pageProvider' => new EmptyPageProvider(),
                'identifierCollection' => new IdentifierCollection([
                    $cssElementIdentifierWithName
                ]),
                'expectedIdentifier' => $cssElementIdentifierWithName,
            ],
        ];
    }

    public function testResolveThrowsUnknownElementException()
    {
        $identifier = ReferenceIdentifier::createElementReferenceIdentifier(
            new ElementReference('$elements.element_name', 'element_name')
        );

        $this->expectException(UnknownElementException::class);
        $this->expectExceptionMessage('Unknown element "element_name"');

        $this->resolver->resolve($identifier, new EmptyPageProvider(), new IdentifierCollection());
    }
}
