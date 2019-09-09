<?php
/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection PhpDocSignatureInspection */

namespace webignition\BasilModelResolver\Tests\Unit;

use Nyholm\Psr7\Uri;
use webignition\BasilModel\Assertion\Assertion;
use webignition\BasilModel\Assertion\AssertionComparisons;
use webignition\BasilModel\Assertion\AssertionInterface;
use webignition\BasilModel\Identifier\AttributeIdentifier;
use webignition\BasilModel\Identifier\ElementIdentifier;
use webignition\BasilModel\Identifier\IdentifierCollection;
use webignition\BasilModel\Identifier\IdentifierCollectionInterface;
use webignition\BasilModel\Page\Page;
use webignition\BasilModel\Value\AttributeValue;
use webignition\BasilModel\Value\CssSelector;
use webignition\BasilModel\Value\ElementValue;
use webignition\BasilModel\Value\LiteralValue;
use webignition\BasilModel\Value\PageProperty;
use webignition\BasilModelFactory\AssertionFactory;
use webignition\BasilModelProvider\Page\EmptyPageProvider;
use webignition\BasilModelProvider\Page\PageProvider;
use webignition\BasilModelProvider\Page\PageProviderInterface;
use webignition\BasilModelResolver\AssertionResolver;
use webignition\BasilTestIdentifierFactory\TestIdentifierFactory;

class AssertionResolverTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var AssertionResolver
     */
    private $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = AssertionResolver::createResolver();
    }

    /**
     * @dataProvider resolveLeavesAssertionUnchangedDataProvider
     */
    public function testResolveLeavesAssertionUnchanged(AssertionInterface $assertion)
    {
        $this->assertEquals(
            $assertion,
            $this->resolver->resolve($assertion, new EmptyPageProvider(), new IdentifierCollection())
        );
    }

    public function resolveLeavesAssertionUnchangedDataProvider(): array
    {
        return [
            'examined value missing' => [
                'assertion' => new Assertion(
                    '',
                    null,
                    ''
                ),
            ],
            'examined value is not object value' => [
                'assertion' => new Assertion(
                    '',
                    new LiteralValue('literal string'),
                    ''
                ),
            ],
            'examined value is not page element reference' => [
                'assertion' => new Assertion(
                    '$page.url is "value"',
                    new PageProperty('$page.url', 'url'),
                    AssertionComparisons::IS,
                    new LiteralValue('value')
                ),
            ],
            'examined value is not an element parameter' => [
                'assertion' => new Assertion(
                    '".selector" is "value"',
                    new ElementValue(
                        new ElementIdentifier(
                            new CssSelector('.selector')
                        )
                    ),
                    AssertionComparisons::IS,
                    new LiteralValue('value')
                ),
            ],
        ];
    }

    /**
     * @dataProvider resolveDataProvider
     */
    public function testResolve(
        AssertionInterface $assertion,
        PageProviderInterface $pageProvider,
        IdentifierCollectionInterface $identifierCollection,
        AssertionInterface $expectedAssertion
    ) {
        $resolvedAssertion = $this->resolver->resolve(
            $assertion,
            $pageProvider,
            $identifierCollection
        );

        $this->assertNotSame($assertion, $resolvedAssertion);
        $this->assertEquals($expectedAssertion, $resolvedAssertion);
    }

    public function resolveDataProvider(): array
    {
        $assertionFactory = AssertionFactory::createFactory();

        $cssIdentifier = TestIdentifierFactory::createElementIdentifier(
            new CssSelector('.selector')
        );

        $namedCssIdentifier = TestIdentifierFactory::createElementIdentifier(
            new CssSelector('.selector'),
            null,
            'element_name'
        );

        $namedExpectedCssIdentifier = TestIdentifierFactory::createElementIdentifier(
            new CssSelector('.expected-selector'),
            1,
            'expected'
        );

        $examinedCssIdentifier = TestIdentifierFactory::createElementIdentifier(
            new CssSelector('.examined-selector')
        );

        $namedExaminedCssIdentifier = TestIdentifierFactory::createElementIdentifier(
            new CssSelector('.examined-selector'),
            1,
            'examined'
        );

        return [
            'examined value is page element reference' => [
                'assertion' => $assertionFactory->createFromAssertionString(
                    'page_import_name.elements.element_name exists'
                ),
                'pageProvider' => new PageProvider([
                    'page_import_name' => new Page(
                        new Uri('http://example.com/'),
                        new IdentifierCollection([
                            $namedCssIdentifier,
                        ])
                    )
                ]),
                'identifierCollection' => new IdentifierCollection(),
                'expectedAssertion' => new Assertion(
                    'page_import_name.elements.element_name exists',
                    new ElementValue($namedCssIdentifier),
                    AssertionComparisons::EXISTS
                ),
            ],
            'expected value is page element reference' => [
                'assertion' => $assertionFactory->createFromAssertionString(
                    '".examined-selector" is page_import_name.elements.expected'
                ),
                'pageProvider' => new PageProvider([
                    'page_import_name' => new Page(
                        new Uri('http://example.com/'),
                        new IdentifierCollection([
                            $namedExpectedCssIdentifier,
                        ])
                    )
                ]),
                'identifierCollection' => new IdentifierCollection(),
                'expectedAssertion' => new Assertion(
                    '".examined-selector" is page_import_name.elements.expected',
                    new ElementValue($examinedCssIdentifier),
                    AssertionComparisons::IS,
                    new ElementValue($namedExpectedCssIdentifier)
                ),
            ],
            'expected and examined values are page element reference' => [
                'assertion' => $assertionFactory->createFromAssertionString(
                    'page_import_name.elements.examined is page_import_name.elements.expected'
                ),
                'pageProvider' => new PageProvider([
                    'page_import_name' => new Page(
                        new Uri('http://example.com/'),
                        new IdentifierCollection([
                            $namedExpectedCssIdentifier,
                            $namedExaminedCssIdentifier,
                        ])
                    )
                ]),
                'identifierCollection' => new IdentifierCollection(),
                'expectedAssertion' => new Assertion(
                    'page_import_name.elements.examined is page_import_name.elements.expected',
                    new ElementValue($namedExaminedCssIdentifier),
                    AssertionComparisons::IS,
                    new ElementValue($namedExpectedCssIdentifier)
                ),
            ],
            'examined value is element parameter' => [
                'assertion' => $assertionFactory->createFromAssertionString('$elements.examined exists'),
                'pageProvider' => new EmptyPageProvider(),
                'identifierCollection' => new IdentifierCollection([
                    $namedExaminedCssIdentifier,
                ]),
                'expectedAssertion' => new Assertion(
                    '$elements.examined exists',
                    new ElementValue($namedExaminedCssIdentifier),
                    AssertionComparisons::EXISTS
                ),
            ],
            'expected value is element parameter' => [
                'assertion' => $assertionFactory->createFromAssertionString(
                    '".selector" is $elements.expected'
                ),
                'pageProvider' => new EmptyPageProvider(),
                'identifierCollection' => new IdentifierCollection([
                    $namedExpectedCssIdentifier
                ]),
                'expectedAssertion' => new Assertion(
                    '".selector" is $elements.expected',
                    new ElementValue($cssIdentifier),
                    AssertionComparisons::IS,
                    new ElementValue($namedExpectedCssIdentifier)
                ),
            ],
            'expected and examined values are element references' => [
                'assertion' => $assertionFactory->createFromAssertionString(
                    '$elements.examined is $elements.expected'
                ),
                'pageProvider' => new EmptyPageProvider(),
                'identifierCollection' => new IdentifierCollection([
                    $namedExpectedCssIdentifier,
                    $namedExaminedCssIdentifier,
                ]),
                'expectedAssertion' => new Assertion(
                    '$elements.examined is $elements.expected',
                    new ElementValue($namedExaminedCssIdentifier),
                    AssertionComparisons::IS,
                    new ElementValue($namedExpectedCssIdentifier)
                ),
            ],
            'expected value is attribute parameter' => [
                'assertion' => $assertionFactory->createFromAssertionString(
                    '$elements.examined.attribute_name exists'
                ),
                'pageProvider' => new EmptyPageProvider(),
                'identifierCollection' => new IdentifierCollection([
                    $namedExaminedCssIdentifier,
                ]),
                'expectedAssertion' => new Assertion(
                    '$elements.examined.attribute_name exists',
                    new AttributeValue(
                        new AttributeIdentifier(
                            $namedExaminedCssIdentifier,
                            'attribute_name'
                        )
                    ),
                    AssertionComparisons::EXISTS
                ),
            ],
            'examined value is attribute parameter' => [
                'assertion' => $assertionFactory->createFromAssertionString(
                    '".selector" is $elements.expected.attribute_name'
                ),
                'pageProvider' => new EmptyPageProvider(),
                'identifierCollection' => new IdentifierCollection([
                    $namedExpectedCssIdentifier,
                ]),
                'expectedAssertion' => new Assertion(
                    '".selector" is $elements.expected.attribute_name',
                    new ElementValue(
                        $cssIdentifier
                    ),
                    AssertionComparisons::IS,
                    new AttributeValue(
                        new AttributeIdentifier(
                            $namedExpectedCssIdentifier,
                            'attribute_name'
                        )
                    )
                ),
            ],
            'examined and expected values are attribute parameter' => [
                'assertion' => $assertionFactory->createFromAssertionString(
                    '$elements.examined.attribute_name is $elements.expected.attribute_name'
                ),
                'pageProvider' => new EmptyPageProvider(),
                'identifierCollection' => new IdentifierCollection([
                    $namedExaminedCssIdentifier,
                    $namedExpectedCssIdentifier,
                ]),
                'expectedAssertion' => new Assertion(
                    '$elements.examined.attribute_name is $elements.expected.attribute_name',
                    new AttributeValue(
                        new AttributeIdentifier(
                            $namedExaminedCssIdentifier,
                            'attribute_name'
                        )
                    ),
                    AssertionComparisons::IS,
                    new AttributeValue(
                        new AttributeIdentifier(
                            $namedExpectedCssIdentifier,
                            'attribute_name'
                        )
                    )
                ),
            ],
        ];
    }
}
