<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Xclass\Deepltranslate\Core;

use ThieleUndKlose\Autotranslate\Utility\TranslationHelper;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\ServerRequestFactory;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use WebVision\Deepltranslate\Core\ConfigurationInterface;

final class Configuration implements ConfigurationInterface, SingletonInterface
{
    private string $apiKey = '';
    private string $modelType = 'prefer_quality_optimized';
    private string $splitSentences = 'on';
    private bool $preserveFormatting = false;
    private string $ignoreTags = '';
    private string $nonSplittingTags = '';
    private string $splittingTags = '';
    private bool $outlineDetection = true;

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
        } else {
            $siteFinder = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Site\SiteFinder::class);
            $sites = $siteFinder->getAllSites();
            foreach ($sites as $site) {
                $apiKeyData = TranslationHelper::apiKey($site->getRootPageId());
                if (!empty($apiKeyData['key'])) {
                    $this->apiKey = $apiKeyData['key'];
                    break; // first found site with API key
                }
            }
        }

        // fallback to api key from deepltranslate core
        $extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('deepltranslate_core');
        if (!$this->apiKey) {
            $this->apiKey = (string)($extensionConfiguration['apiKey'] ?? '');
        }

        $this->modelType = (string)($extensionConfiguration['modelType'] ?? 'prefer_quality_optimized');
        $this->splitSentences = (string)($extensionConfiguration['splitSentences'] ?? 'on');
        $this->preserveFormatting = (bool)($extensionConfiguration['preserverFormatting'] ?? false);
        $this->ignoreTags = (string)($extensionConfiguration['ignoreTags'] ?? '');
        $this->nonSplittingTags = (string)($extensionConfiguration['nonSplittingTags'] ?? '');
        $this->splittingTags = (string)($extensionConfiguration['splittingTags'] ?? '');
        $this->outlineDetection = (bool)($extensionConfiguration['outlineDetection'] ?? true);
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function getModelType(): string
    {
        return $this->modelType;
    }

    public function getSplitSentences(): string
    {
        return $this->splitSentences;
    }

    public function isPreserveFormattingEnabled(): bool
    {
        return $this->preserveFormatting;
    }

    public function getIgnoreTags(): string
    {
        return $this->ignoreTags;
    }

    public function getNonSplittingTags(): string
    {
        return $this->nonSplittingTags;
    }

    public function getSplittingTags(): string
    {
        return $this->splittingTags;
    }

    public function isOutlineDetectionEnabled(): bool
    {
        return $this->outlineDetection;
    }
}
