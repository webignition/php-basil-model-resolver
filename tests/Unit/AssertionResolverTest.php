<?php
/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection PhpDocSignatureInspection */

namespace webignition\BasilModelResolver\Tests\Unit;

use Nyholm\Psr7\Uri;
use webignition\BasilModel\Assertion\AssertionComparison;
use webignition\BasilModel\Assertion\AssertionInterface;
use webignition\BasilModel\Assertion\ComparisonAssertion;
use webignition\BasilModel\Assertion\ExaminationAssertion;
use webignition\BasilModel\Identifier\AttributeIdentifier;
use webignition\BasilModel\Identifier\IdentifierCollection;
use webignition\BasilModel\Identifier\IdentifierCollectionInterface;
use webignition\BasilModel\Page\Page;
use webignition\BasilModel\Value\AssertionExaminedValue;
use webignition\BasilModel\Value\AssertionExpectedValue;
use webignition\BasilModel\Value\AttributeValue;
use webignition\BasilModel\Value\CssSelector;
use webignition\BasilModel\Value\ElementValue;
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
                'expectedAssertion' => new ExaminationAssertion(
                    'page_import_name.elements.element_name exists',
                    new AssertionExaminedValue(new ElementValue($namedCssIdentifier)),
                    AssertionComparison::EXISTS
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
                'expectedAssertion' => new ComparisonAssertion(
                    '".examined-selector" is page_import_name.elements.expected',
                    new AssertionExaminedValue(new ElementValue($examinedCssIdentifier)),
                    AssertionComparison::IS,
                    new AssertionExpectedValue(new ElementValue($namedExpectedCssIdentifier))
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
                'expectedAssertion' => new ComparisonAssertion(
                    'page_import_name.elements.examined is page_import_name.elements.expected',
                    new AssertionExaminedValue(new ElementValue($namedExaminedCssIdentifier)),
                    AssertionComparison::IS,
                    new AssertionExpectedValue(new ElementValue($namedExpectedCssIdentifier))
                ),
            ],
            'examined value is element parameter' => [
                'assertion' => $assertionFactory->createFromAssertionString('$elements.examined exists'),
                'pageProvider' => new EmptyPageProvider(),
                'identifierCollection' => new IdentifierCollection([
                    $namedExaminedCssIdentifier,
                ]),
                'expectedAssertion' => new ExaminationAssertion(
                    '$elements.examined exists',
                    new AssertionExaminedValue(new ElementValue($namedExaminedCssIdentifier)),
                    AssertionComparison::EXISTS
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
                'expectedAssertion' => new ComparisonAssertion(
                    '".selector" is $elements.expected',
                    new AssertionExaminedValue(new ElementValue($cssIdentifier)),
                    AssertionComparison::IS,
                    new AssertionExpectedValue(new ElementValue($namedExpectedCssIdentifier))
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
                'expectedAssertion' => new ComparisonAssertion(
                    '$elements.examined is $elements.expected',
                    new AssertionExaminedValue(new ElementValue($namedExaminedCssIdentifier)),
                    AssertionComparison::IS,
                    new AssertionExpectedValue(new ElementValue($namedExpectedCssIdentifier))
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
                'expectedAssertion' => new ExaminationAssertion(
                    '$elements.examined.attribute_name exists',
                    new AssertionExaminedValue(new AttributeValue(
                        new AttributeIdentifier(
                            $namedExaminedCssIdentifier,
                            'attribute_name'
                        )
                    )),
                    AssertionComparison::EXISTS
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
                'expectedAssertion' => new ComparisonAssertion(
                    '".selector" is $elements.expected.attribute_name',
                    new AssertionExaminedValue(
                        new ElementValue(
                            $cssIdentifier
                        )
                    ),
                    AssertionComparison::IS,
                    new AssertionExpectedValue(
                        new AttributeValue(
                            new AttributeIdentifier(
                                $namedExpectedCssIdentifier,
                                'attribute_name'
                            )
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
                'expectedAssertion' => new ComparisonAssertion(
                    '$elements.examined.attribute_name is $elements.expected.attribute_name',
                    new AssertionExaminedValue(
                        new AttributeValue(
                            new AttributeIdentifier(
                                $namedExaminedCssIdentifier,
                                'attribute_name'
                            )
                        )
                    ),
                    AssertionComparison::IS,
                    new AssertionExpectedValue(
                        new AttributeValue(
                            new AttributeIdentifier(
                                $namedExpectedCssIdentifier,
                                'attribute_name'
                            )
                        )
                    )
                ),
            ],
        ];
    }
}
