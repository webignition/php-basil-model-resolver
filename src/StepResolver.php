<?php

namespace webignition\BasilModelResolver;

use webignition\BasilContextAwareException\ExceptionContext\ExceptionContextInterface;
use webignition\BasilModel\Action\ActionInterface;
use webignition\BasilModel\Assertion\AssertionInterface;
use webignition\BasilModel\Identifier\IdentifierCollection;
use webignition\BasilModel\Step\PendingImportResolutionStep;
use webignition\BasilModel\Step\StepInterface;
use webignition\BasilModelProvider\Exception\UnknownPageException;
use webignition\BasilModelProvider\Page\PageProviderInterface;

class StepResolver
{
    private $actionResolver;
    private $assertionResolver;
    private $identifierResolver;

    public function __construct(
        ActionResolver $actionResolver,
        AssertionResolver $assertionResolver,
        IdentifierResolver $identifierResolver
    ) {
        $this->actionResolver = $actionResolver;
        $this->assertionResolver = $assertionResolver;
        $this->identifierResolver = $identifierResolver;
    }

    public static function createResolver(): StepResolver
    {
        return new StepResolver(
            ActionResolver::createResolver(),
            AssertionResolver::createResolver(),
            IdentifierResolver::createResolver()
        );
    }

    /**
     * @param StepInterface $step
     * @param PageProviderInterface $pageProvider
     *
     * @return StepInterface
     *
     * @throws UnknownElementException
     * @throws UnknownPageElementException
     * @throws UnknownPageException
     */
    public function resolve(StepInterface $step, PageProviderInterface $pageProvider): StepInterface
    {
        if ($step instanceof PendingImportResolutionStep) {
            if ($step->requiresResolution()) {
                return $step;
            }

            $step = $step->getStep();
        }

        $step = $this->resolveIdentifierCollection($step, $pageProvider);
        $step = $this->resolveActions($step, $pageProvider);
        $step = $this->resolveAssertions($step, $pageProvider);

        return $step;
    }

    /**
     * @param StepInterface $step
     * @param PageProviderInterface $pageProvider
     *
     * @return StepInterface
     *
     * @throws UnknownElementException
     * @throws UnknownPageElementException
     * @throws UnknownPageException
     */
    private function resolveIdentifierCollection(
        StepInterface $step,
        PageProviderInterface $pageProvider
    ): StepInterface {
        $resolvedIdentifiers = [];
        foreach ($step->getIdentifierCollection() as $identifier) {
            $resolvedIdentifiers[] = $this->identifierResolver->resolve(
                $identifier,
                $pageProvider,
                new IdentifierCollection()
            );
        }

        return $step->withIdentifierCollection(new IdentifierCollection($resolvedIdentifiers));
    }

    /**
     * @param StepInterface $step
     * @param PageProviderInterface $pageProvider
     *
     * @return StepInterface
     *
     * @throws UnknownElementException
     * @throws UnknownPageElementException
     * @throws UnknownPageException
     */
    private function resolveActions(
        StepInterface $step,
        PageProviderInterface $pageProvider
    ): StepInterface {
        $resolvedActions = [];
        $action = null;

        $identifierCollection = $step->getIdentifierCollection();

        try {
            foreach ($step->getActions() as $action) {
                $resolvedActions[] = $this->actionResolver->resolve(
                    $action,
                    $pageProvider,
                    $identifierCollection
                );
            }
        } catch (UnknownElementException |
            UnknownPageElementException |
            UnknownPageException $contextAwareException
        ) {
            if ($action instanceof ActionInterface) {
                $contextAwareException->applyExceptionContext([
                    ExceptionContextInterface::KEY_CONTENT => $action->getActionString(),
                ]);
            }

            throw $contextAwareException;
        }

        return $step->withActions($resolvedActions);
    }

    /**
     * @param StepInterface $step
     * @param PageProviderInterface $pageProvider
     *
     * @return StepInterface
     *
     * @throws UnknownElementException
     * @throws UnknownPageElementException
     * @throws UnknownPageException
     */
    private function resolveAssertions(
        StepInterface $step,
        PageProviderInterface $pageProvider
    ): StepInterface {
        $resolvedAssertions = [];
        $assertion = null;

        $identifierCollection = $step->getIdentifierCollection();

        try {
            foreach ($step->getAssertions() as $assertion) {
                $resolvedAssertions[] = $this->assertionResolver->resolve(
                    $assertion,
                    $pageProvider,
                    $identifierCollection
                );
            }
        } catch (UnknownElementException |
            UnknownPageElementException |
            UnknownPageException $contextAwareException
        ) {
            $exceptionContextContent = null;

            if ($assertion instanceof AssertionInterface) {
                $contextAwareException->applyExceptionContext([
                    ExceptionContextInterface::KEY_CONTENT => $assertion->getAssertionString(),
                ]);
            }

            throw $contextAwareException;
        }

        return $step->withAssertions($resolvedAssertions);
    }
}
