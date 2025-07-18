<?php
declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Utility;

use DeepL\Translator;
use DeepL\AuthorizationException;
use DeepL\DeepLException;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class DeeplApiHelper
{
    /**
     * Prüft, ob der DeepL API-Key gültig ist und gibt optional die Usage zurück.
     *
     * @param ?string $apiKey
     * @return array ['isValid' => bool, 'usage' => \DeepL\Usage|null, 'error' => string|null]
     */
    public static function checkApiKey(?string $apiKey): array
    {
        if (!$apiKey) {
            return [
                'isValid' => false,
                'usage' => null,
                'charactersLeft' => 0,
                'error' => null,
            ];
        }

        try {
            $translator = new Translator($apiKey);
            $usage = $translator->getUsage();
            $charactersLeft = null;
            if (is_object($usage) && isset($usage->character)) {
                $charactersLeft = $usage->character->limit - $usage->character->count;
            }
            return [
                'isValid' => true,
                'usage' => $usage,
                'charactersLeft' => $charactersLeft,
                'error' => null,
            ];
        } catch (AuthorizationException $e) {
            return [
                'isValid' => false,
                'usage' => null,
                'charactersLeft' => 0,
                'error' => $e->getMessage(),
            ];
        } catch (DeepLException $e) {
            return [
                'isValid' => false,
                'usage' => null,
                'charactersLeft' => 0,
                'error' => 'DeepL error: ' . $e->getMessage(),
            ];
        } catch (\Throwable $e) {
            return [
                'isValid' => false,
                'usage' => null,
                'charactersLeft' => 0,
                'error' => 'Unexpected error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get DeepL languages (source or target) with caching in var/cache/autotranslate.
     *
     * @param string $apiKey
     * @param string $type 'source' or 'target'
     * @return array
     */
    public static function getCachedLanguages(string $apiKey, string $type = 'source'): array
    {
        $cacheIdentifier = 'deepl_' . $type . '_languages_' . md5($apiKey);

        /** @var \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend $cache */
        $cache = null;
        try {
            $cache = GeneralUtility::makeInstance(
                CacheManager::class
            )->getCache('autotranslate');
        } catch (NoSuchCacheException $e) {
            // Cache not configured, fallback to direct API call
        }

        // Try to fetch from cache
        if ($cache && $cache->has($cacheIdentifier)) {
            $data = $cache->get($cacheIdentifier);
            if (is_array($data)) {
                return $data;
            }
        }

        // Not in cache: fetch from DeepL
        try {
            $translator = new \DeepL\Translator($apiKey);
            if ($type === 'source') {
                $languages = $translator->getSourceLanguages();
            } else {
                $languages = $translator->getTargetLanguages();
            }
            $result = [];
            foreach ($languages as $language) {
                $result[] = [$language->name, $language->code];
            }
            if ($cache) {
                $cache->set($cacheIdentifier, $result, [], 86400 * 7); // 7 days cache-lifetime
            }
            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }
}
