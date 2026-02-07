<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Utility;

use DeepL\AuthorizationException;
use DeepL\DeepLException;
use DeepL\Translator;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class DeeplApiHelper
{
    /**
     * Validate the DeepL API key and return usage information.
     *
     * @return array{isValid: bool, usage: mixed, charactersLeft: ?int, error: ?string}
     */
    public static function checkApiKey(?string $apiKey): array
    {
        if (!$apiKey) {
            return ['isValid' => false, 'usage' => null, 'charactersLeft' => 0, 'error' => null];
        }

        try {
            $translator = new Translator($apiKey);
            $usage = $translator->getUsage();
            $charactersLeft = isset($usage->character)
                ? $usage->character->limit - $usage->character->count
                : null;

            return ['isValid' => true, 'usage' => $usage, 'charactersLeft' => $charactersLeft, 'error' => null];
        } catch (AuthorizationException $e) {
            return ['isValid' => false, 'usage' => null, 'charactersLeft' => 0, 'error' => $e->getMessage()];
        } catch (DeepLException $e) {
            return ['isValid' => false, 'usage' => null, 'charactersLeft' => 0, 'error' => 'DeepL error: ' . $e->getMessage()];
        } catch (\Throwable $e) {
            return ['isValid' => false, 'usage' => null, 'charactersLeft' => 0, 'error' => 'Unexpected error: ' . $e->getMessage()];
        }
    }

    /**
     * Get DeepL languages (source or target) with caching.
     *
     * @param string $type 'source' or 'target'
     * @return array<int, array{0: string, 1: string}>
     */
    public static function getCachedLanguages(string $apiKey, string $type = 'source'): array
    {
        if (!in_array($type, ['source', 'target'], true)) {
            throw new \InvalidArgumentException(sprintf('Invalid type "%s". Allowed values are "source" or "target".', $type));
        }

        $cacheIdentifier = 'deepl_' . $type . '_languages_' . md5($apiKey);

        try {
            $cache = GeneralUtility::makeInstance(CacheManager::class)->getCache('autotranslate');
        } catch (NoSuchCacheException) {
            $cache = null;
        }

        if ($cache?->has($cacheIdentifier)) {
            $data = $cache->get($cacheIdentifier);
            if (is_array($data)) {
                return $data;
            }
        }

        try {
            $translator = new Translator($apiKey);
            // @extensionScannerIgnoreLine
            $languages = $type === 'source'
                ? $translator->getSourceLanguages()
                : $translator->getTargetLanguages();

            $result = [];
            foreach ($languages as $language) {
                // @extensionScannerIgnoreLine
                $result[] = [$language->name, $language->code];
            }

            $cache?->set($cacheIdentifier, $result, [], 86400 * 7);

            return $result;
        } catch (\Exception) {
            return [];
        }
    }
}
