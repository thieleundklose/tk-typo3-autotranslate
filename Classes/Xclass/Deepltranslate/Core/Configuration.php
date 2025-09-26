<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Xclass\Deepltranslate\Core;

use ThieleUndKlose\Autotranslate\Utility\TranslationHelper;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\ServerRequestFactory;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use WebVision\Deepltranslate\Core\ConfigurationInterface;

final class Configuration implements ConfigurationInterface, SingletonInterface
{
    private string $apiKey = '';

    /**
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     */
    public function __construct()
    {
        if (!Environment::isCli()) {
            $request = $GLOBALS['TYPO3_REQUEST'] ?? ServerRequestFactory::fromGlobals();
            $processingParameters = $request->getQueryParams();
            if ($processingParameters['uid'] ?? null) {
                list('key' => $this->apiKey) = TranslationHelper::apiKey((int)$processingParameters['uid']) ?? '';
            }
        }

        // fallback to api key from deepltranslate core
        if (!$this->apiKey) {
            $extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('deepltranslate_core');
            $this->apiKey = (string)($extensionConfiguration['apiKey'] ?? '');
        }
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }
}
