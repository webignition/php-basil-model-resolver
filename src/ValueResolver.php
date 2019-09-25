<?php

namespace webignition\BasilModelResolver;

use webignition\BasilModel\Identifier\DomIdentifierInterface;
use webignition\BasilModel\Identifier\IdentifierCollectionInterface;
use webignition\BasilModel\Value\DomIdentifierReferenceInterface;
use webignition\BasilModel\Value\DomIdentifierReferenceType;
use webignition\BasilModel\Value\DomIdentifierValue;
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
            return new DomIdentifierValue(
                $this->pageElementReferenceResolver->resolve($value, $pageProvider)
            );
        }

        if ($value instanceof DomIdentifierReferenceInterface) {
            if (DomIdentifierReferenceType::ELEMENT === $value->getType()) {
                return new DomIdentifierValue(
                    $this->findElementIdentifier($identifierCollection, $value->getProperty())
                );
            }

            if (DomIdentifierReferenceType::ATTRIBUTE === $value->getType()) {
                $property = $value->getProperty();

                if (substr_count($property, self::ELEMENT_NAME_ATTRIBUTE_NAME_DELIMITER) > 0) {
                    list($elementName, $attributeName) = explode('.', $property);

                    $elementIdentifier = $this->findElementIdentifier($identifierCollection, $elementName);
                    $elementIdentifier = $elementIdentifier->withAttributeName($attributeName);

                    return new DomIdentifierValue($elementIdentifier);
                }
            }
        }

        return $value;
    }

    /**
     * @param IdentifierCollectionInterface $identifierCollection
     *
     * @param string $elementName
     *
     * @return DomIdentifierInterface
     * @throws UnknownElementException
     */
    private function findElementIdentifier(
        IdentifierCollectionInterface $identifierCollection,
        string $elementName
    ): DomIdentifierInterface {
        $identifier = $identifierCollection->getIdentifier($elementName);

        if (!$identifier instanceof DomIdentifierInterface) {
            throw new UnknownElementException($elementName);
        }

        return $identifier;
    }
}
