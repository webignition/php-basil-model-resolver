<?php

namespace webignition\BasilModelResolver;

use webignition\BasilModel\Identifier\DomIdentifierInterface;
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
     * @return DomIdentifierInterface
     *
     * @throws UnknownPageElementException
     * @throws UnknownPageException
     */
    public function resolve(
        PageElementReference $value,
        PageProviderInterface $pageProvider
    ): DomIdentifierInterface {
        $page = $pageProvider->findPage($value->getObject());
        $identifier = $page->getIdentifier($value->getProperty());

        if ($identifier instanceof DomIdentifierInterface) {
            return $identifier;
        }

        throw new UnknownPageElementException($value->getObject(), $value->getProperty());
    }
}
