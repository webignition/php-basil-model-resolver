<?php

namespace webignition\BasilModelResolver;

use webignition\BasilModel\Action\ActionInterface;
use webignition\BasilModel\Action\InputActionInterface;
use webignition\BasilModel\Action\InteractionActionInterface;
use webignition\BasilModel\Identifier\IdentifierCollectionInterface;
use webignition\BasilModel\Identifier\IdentifierInterface;
use webignition\BasilModelProvider\Exception\UnknownPageException;
use webignition\BasilModelProvider\Page\PageProviderInterface;

class ActionResolver
{
    private $identifierResolver;
    private $valueResolver;

    public function __construct(IdentifierResolver $identifierResolver, ValueResolver $valueResolver)
    {
        $this->identifierResolver = $identifierResolver;
        $this->valueResolver = $valueResolver;
    }

    public static function createResolver(): ActionResolver
    {
        return new ActionResolver(
            IdentifierResolver::createResolver(),
            ValueResolver::createResolver()
        );
    }

    /**
     * @param ActionInterface $action
     * @param PageProviderInterface $pageProvider
     * @param IdentifierCollectionInterface $identifierCollection
     *
     * @return ActionInterface
     *
     * @throws UnknownElementException
     * @throws UnknownPageElementException
     * @throws UnknownPageException
     */
    public function resolve(
        ActionInterface $action,
        PageProviderInterface $pageProvider,
        IdentifierCollectionInterface $identifierCollection
    ): ActionInterface {
        if (!$action instanceof InteractionActionInterface) {
            return $action;
        }

        $identifier = $action->getIdentifier();

        if ($identifier instanceof IdentifierInterface) {
            $resolvedIdentifier = $this->identifierResolver->resolve($identifier, $pageProvider, $identifierCollection);

            if ($resolvedIdentifier !== $identifier) {
                $action = $action->withIdentifier($resolvedIdentifier);
            }
        }

        if ($action instanceof InputActionInterface) {
            $resolvedValue = $this->valueResolver->resolve($action->getValue(), $pageProvider, $identifierCollection);

            $action = $action->withValue($resolvedValue);
        }

        return $action;
    }
}
