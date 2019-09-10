<?php

namespace webignition\BasilModelResolver;

use webignition\BasilModel\Identifier\AttributeIdentifier;
use webignition\BasilModel\Identifier\ElementIdentifierInterface;
use webignition\BasilModel\Identifier\IdentifierCollectionInterface;
use webignition\BasilModel\Value\AttributeReference;
use webignition\BasilModel\Value\AttributeValue;
use webignition\BasilModel\Value\ElementReference;
use webignition\BasilModel\Value\ElementValue;
use webignition\BasilModel\Value\PageElementReference;
use webignition\BasilModel\Value\ValueInterface;
use webignition\BasilModelProvider\Exception\UnknownPageException;
use webignition\BasilModelProvider\Page\PageProviderInterface;

class ValueResolver
{
    const ELEMENT_NAME_ATTRIBUTE_NAME_DELIMITER = '.';

    private $pageElementReferenceResolver;

    public function __construct(PageElementReferenceResolver $pageElementReferenceResolver)
    {
        $this->pageElementReferenceResolver = $pageElementReferenceResolver;
    }

    public static function createResolver(): ValueResolver
    {
        return new ValueResolver(
            PageElementReferenceResolver::createResolver()
        );
    }

    /**
     * @param ValueInterface $value
     * @param PageProviderInterface $pageProvider
     * @param IdentifierCollectionInterface $identifierCollection
     *
     * @return ValueInterface
     *
     * @throws UnknownElementException
     * @throws UnknownPageElementException
     * @throws UnknownPageException
     */
    public function resolve(
        ValueInterface $value,
        PageProviderInterface $pageProvider,
        IdentifierCollectionInterface $identifierCollection
    ): ValueInterface {
        if ($value instanceof PageElementReference) {
            return new ElementValue(
                $this->pageElementReferenceResolver->resolve($value, $pageProvider)
            );
        }

        if ($value instanceof ElementReference) {
            return new ElementValue(
                $this->findElementIdentifier($identifierCollection, $value->getProperty())
            );
        }

        if ($value instanceof AttributeReference) {
            $property = $value->getProperty();

            if (substr_count($property, self::ELEMENT_NAME_ATTRIBUTE_NAME_DELIMITER) > 0) {
                list($elementName, $attributeName) = explode('.', $property);

                $elementIdentifier = $this->findElementIdentifier($identifierCollection, $elementName);
                $attributeIdentifier = new AttributeIdentifier($elementIdentifier, $attributeName);

                return new AttributeValue($attributeIdentifier);
            }
        }

        return $value;
    }

    /**
     * @param IdentifierCollectionInterface $identifierCollection
     *
     * @param string $elementName
     *
     * @return ElementIdentifierInterface
     * @throws UnknownElementException
     */
    private function findElementIdentifier(
        IdentifierCollectionInterface $identifierCollection,
        string $elementName
    ): ElementIdentifierInterface {
        $identifier = $identifierCollection->getIdentifier($elementName);

        if (!$identifier instanceof ElementIdentifierInterface) {
            throw new UnknownElementException($elementName);
        }

        return $identifier;
    }
}
