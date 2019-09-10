<?php

namespace webignition\BasilModelResolver;

use webignition\BasilModel\Identifier\ElementIdentifierInterface;
use webignition\BasilModel\Value\PageElementReference;
use webignition\BasilModelProvider\Exception\UnknownPageException;
use webignition\BasilModelProvider\Page\PageProviderInterface;

class PageElementReferenceResolver
{
    public static function createResolver(): PageElementReferenceResolver
    {
        return new PageElementReferenceResolver();
    }

    /**
     * @param PageElementReference $value
     * @param PageProviderInterface $pageProvider
     *
     * @return ElementIdentifierInterface
     *
     * @throws UnknownPageElementException
     * @throws UnknownPageException
     */
    public function resolve(
        PageElementReference $value,
        PageProviderInterface $pageProvider
    ): ElementIdentifierInterface {
        $page = $pageProvider->findPage($value->getObject());
        $elementIdentifier = $page->getIdentifier($value->getProperty());

        if ($elementIdentifier instanceof ElementIdentifierInterface) {
            return $elementIdentifier;
        }

        throw new UnknownPageElementException($value->getObject(), $value->getProperty());
    }
}
