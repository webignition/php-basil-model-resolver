<?php

namespace webignition\BasilModelResolver;

use webignition\BasilModel\Identifier\DomIdentifierInterface;
use webignition\BasilModel\Identifier\IdentifierCollectionInterface;
use webignition\BasilModel\Identifier\IdentifierInterface;
use webignition\BasilModel\Identifier\ReferenceIdentifierInterface;
use webignition\BasilModel\Identifier\ReferenceIdentifierTypes;
use webignition\BasilModel\Value\DomIdentifierReference;
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
        if ($identifier instanceof ReferenceIdentifierInterface) {
            if (ReferenceIdentifierTypes::PAGE_ELEMENT_REFERENCE === $identifier->getType()) {
                $value = $identifier->getValue();

                if ($value instanceof PageElementReference) {
                    return $this->pageElementReferenceResolver->resolve($value, $pageProvider);
                }
            }

            if (ReferenceIdentifierTypes::ELEMENT_REFERENCE === $identifier->getType()) {
                $value = $identifier->getValue();

                if ($value instanceof DomIdentifierReference) {
                    $elementName = $value->getProperty();
                    $resolvedIdentifier = $identifierCollection->getIdentifier($elementName);

                    if ($resolvedIdentifier instanceof DomIdentifierInterface) {
                        return $resolvedIdentifier;
                    }

                    throw new UnknownElementException($elementName);
                }
            }
        }

        return $identifier;
    }
}
