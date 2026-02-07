<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Xclass\Deepltranslate\Core;

use ThieleUndKlose\Autotranslate\Utility\TranslationHelper;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\ServerRequestFactory;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use WebVision\Deepltranslate\Core\ConfigurationInterface;

/**
 * Custom configuration class for DeepL API integration
 *
 * This class extends the deepltranslate_core configuration to support
 * site-specific API keys configured via TYPO3 site configuration.
 */
final class Configuration implements ConfigurationInterface, SingletonInterface
{
    private string $apiKey = '';

    public function __construct()
    {
        $this->apiKey = $this->resolveApiKey();
    }

    /**
     * Resolve the API key from various sources
     */
    private function resolveApiKey(): string
    {
        // Try to get API key from current request context
        $apiKey = $this->getApiKeyFromRequest();

        // Fallback: Get API key from first available site (CLI context)
        if (empty($apiKey) && Environment::isCli()) {
            $apiKey = $this->getApiKeyFromSites();
        }

        // Final fallback: Get API key from deepltranslate_core extension configuration
        if (empty($apiKey)) {
            $apiKey = $this->getApiKeyFromExtensionConfiguration();
        }

        return $apiKey;
    }

    /**
     * Get API key from current HTTP request parameters
     */
    private function getApiKeyFromRequest(): string
    {
        if (Environment::isCli()) {
            return '';
        }

        try {
            $request = $GLOBALS['TYPO3_REQUEST'] ?? ServerRequestFactory::fromGlobals();
            $params = $request->getQueryParams();

            if (!empty($params['uid'])) {
                $apiKeyData = TranslationHelper::apiKey((int)$params['uid']);
                return $apiKeyData['key'] ?? '';
            }
        } catch (\Exception) {
            // Silently fail and try next method
        }

        return '';
    }

    /**
     * Get API key from first site that has one configured
     */
    private function getApiKeyFromSites(): string
    {
        try {
            $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);

            foreach ($siteFinder->getAllSites() as $site) {
                $apiKeyData = TranslationHelper::apiKey($site->getRootPageId());

                if (!empty($apiKeyData['key'])) {
                    return $apiKeyData['key'];
                }
            }
        } catch (\Exception) {
            // Silently fail and try next method
        }

        return '';
    }

    /**
     * Get API key from deepltranslate_core extension configuration
     */
    private function getApiKeyFromExtensionConfiguration(): string
    {
        try {
            $config = GeneralUtility::makeInstance(ExtensionConfiguration::class)
                ->get('deepltranslate_core');

            return (string)($config['apiKey'] ?? '');
        } catch (\Exception) {
            return '';
        }
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }
}
