<?php

namespace webignition\BasilModelResolver;

use webignition\BasilModel\Identifier\ElementIdentifierInterface;
use webignition\BasilModel\Value\ObjectValueInterface;
use webignition\BasilModelProvider\Exception\UnknownPageException;
use webignition\BasilModelProvider\Page\PageProviderInterface;

class PageElementReferenceResolver
{
    public static function createResolver(): PageElementReferenceResolver
    {
        return new PageElementReferenceResolver();
    }

    /**
     * @param ObjectValueInterface $value
     * @param PageProviderInterface $pageProvider
     *
     * @return ElementIdentifierInterface
     *
     * @throws UnknownPageElementException
     * @throws UnknownPageException
     */
    public function resolve(
        ObjectValueInterface $value,
        PageProviderInterface $pageProvider
    ): ElementIdentifierInterface {
        $page = $pageProvider->findPage($value->getObjectName());
        $elementIdentifier = $page->getIdentifier($value->getObjectProperty());

        if ($elementIdentifier instanceof ElementIdentifierInterface) {
            return $elementIdentifier;
        }

        throw new UnknownPageElementException($value->getObjectName(), $value->getObjectProperty());
    }
}
