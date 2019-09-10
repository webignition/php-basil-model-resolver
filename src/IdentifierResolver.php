<?php

namespace webignition\BasilModelResolver;

use webignition\BasilModel\Identifier\ElementIdentifierInterface;
use webignition\BasilModel\Identifier\IdentifierCollectionInterface;
use webignition\BasilModel\Identifier\IdentifierInterface;
use webignition\BasilModel\Identifier\IdentifierTypes;
use webignition\BasilModel\Identifier\ReferenceIdentifier;
use webignition\BasilModel\Value\ElementReference;
use webignition\BasilModel\Value\PageElementReference;
use webignition\BasilModelProvider\Exception\UnknownPageException;
use webignition\BasilModelProvider\Page\PageProviderInterface;

class IdentifierResolver
{
    private $pageElementReferenceResolver;

    public function __construct(PageElementReferenceResolver $pageElementReferenceResolver)
    {
        $this->pageElementReferenceResolver = $pageElementReferenceResolver;
    }

    public static function createResolver(): IdentifierResolver
    {
        return new IdentifierResolver(
            PageElementReferenceResolver::createResolver()
        );
    }

    /**
     * @param IdentifierInterface $identifier
     * @param PageProviderInterface $pageProvider
     * @param IdentifierCollectionInterface $identifierCollection
     *
     * @return IdentifierInterface
     *
     * @throws UnknownElementException
     * @throws UnknownPageElementException
     * @throws UnknownPageException
     */
    public function resolve(
        IdentifierInterface $identifier,
        PageProviderInterface $pageProvider,
        IdentifierCollectionInterface $identifierCollection
    ): IdentifierInterface {
        if ($identifier instanceof ReferenceIdentifier) {
            if (IdentifierTypes::PAGE_ELEMENT_REFERENCE === $identifier->getType()) {
                $value = $identifier->getValue();

                if ($value instanceof PageElementReference) {
                    return $this->pageElementReferenceResolver->resolve($value, $pageProvider);
                }
            }

            if (IdentifierTypes::ELEMENT_PARAMETER === $identifier->getType()) {
                $value = $identifier->getValue();

                if ($value instanceof ElementReference) {
                    $elementName = $value->getProperty();
                    $resolvedIdentifier = $identifierCollection->getIdentifier($elementName);

                    if ($resolvedIdentifier instanceof ElementIdentifierInterface) {
                        return $resolvedIdentifier;
                    }

                    throw new UnknownElementException($elementName);
                }
            }
        }

        return $identifier;
    }
}
