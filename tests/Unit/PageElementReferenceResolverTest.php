<?php
/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection PhpDocSignatureInspection */

namespace webignition\BasilModelResolver\Tests\Unit;

use Nyholm\Psr7\Uri;
use webignition\BasilModel\Identifier\IdentifierCollection;
use webignition\BasilModel\Identifier\IdentifierInterface;
use webignition\BasilModel\Page\Page;
use webignition\BasilModel\Value\ElementExpression;
use webignition\BasilModel\Value\ElementExpressionType;
use webignition\BasilModel\Value\PageElementReference;
use webignition\BasilModelProvider\Page\PageProvider;
use webignition\BasilModelProvider\Page\PageProviderInterface;
use webignition\BasilModelResolver\PageElementReferenceResolver;
use webignition\BasilModelResolver\UnknownPageElementException;
use webignition\BasilTestIdentifierFactory\TestIdentifierFactory;

class PageElementReferenceResolverTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var PageElementReferenceResolver
     */
    private $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = PageElementReferenceResolver::createResolver();
    }

    /**
     * @dataProvider resolveIsResolvedDataProvider
     */
    public function testResolveIsResolved(
        PageElementReference $value,
        PageProviderInterface $pageProvider,
        IdentifierInterface $expectedIdentifier
    ) {
        $identifier = $this->resolver->resolve($value, $pageProvider);

        $this->assertEquals($expectedIdentifier, $identifier);
    }

    public function resolveIsResolvedDataProvider(): array
    {
        $cssElementIdentifier = TestIdentifierFactory::createElementIdentifier(
            new ElementExpression('.selector', ElementExpressionType::CSS_SELECTOR)
        );
        $cssElementIdentifierWithName = $cssElementIdentifier->withName('element_name');

        return [
            'resolvable' => [
                'value' => new PageElementReference(
                    'page_import_name.elements.element_name',
                    'page_import_name',
                    'element_name'
                ),
                'pageProvider' => new PageProvider([
                    'page_import_name' => new Page(
                        new Uri('http://example.com/'),
                        new IdentifierCollection([
                            $cssElementIdentifierWithName,
                        ])
                    )
                ]),
                'expectedIdentifier' => $cssElementIdentifierWithName,
            ],
        ];
    }

    /**
     * @dataProvider resolveThrowsUnknownPageElementExceptionDataProvider
     */
    public function testResolveThrowsUnknownPageElementException(
        PageElementReference $value,
        PageProviderInterface $pageProvider,
        string $expectedExceptionMessage
    ) {
        $this->expectException(UnknownPageElementException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);

        $this->resolver->resolve($value, $pageProvider);
    }

    public function resolveThrowsUnknownPageElementExceptionDataProvider(): array
    {
        return [
            'element not present in page' => [
                'value' => new PageElementReference(
                    'page_import_name.elements.element_name',
                    'page_import_name',
                    'element_name'
                ),
                'pageProvider' => new PageProvider([
                    'page_import_name' => new Page(
                        new Uri('http://example.com/'),
                        new IdentifierCollection([])
                    )
                ]),
                'expectedExceptionMessage' => 'Unknown page element "element_name" in page "page_import_name"',
            ],
        ];
    }
}
