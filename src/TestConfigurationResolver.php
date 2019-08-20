<?php

namespace webignition\BasilModelResolver;

use webignition\BasilModel\PageUrlReference\PageUrlReference;
use webignition\BasilModel\Test\Configuration;
use webignition\BasilModel\Test\ConfigurationInterface;
use webignition\BasilModelProvider\Exception\UnknownPageException;
use webignition\BasilModelProvider\Page\PageProviderInterface;

class TestConfigurationResolver
{
    public static function createResolver(): TestConfigurationResolver
    {
        return new TestConfigurationResolver();
    }

    /**
     * @param ConfigurationInterface $configuration
     * @param PageProviderInterface $pageProvider
     *
     * @return ConfigurationInterface
     *
     * @throws UnknownPageException
     */
    public function resolve(
        ConfigurationInterface $configuration,
        PageProviderInterface $pageProvider
    ): ConfigurationInterface {
        $url = $configuration->getUrl();

        $pageUrlReference = new PageUrlReference($url);
        if ($pageUrlReference->isValid()) {
            $page = $pageProvider->findPage($pageUrlReference->getImportName());
            $url = (string) $page->getUri();
        }

        return new Configuration($configuration->getBrowser(), $url);
    }
}
