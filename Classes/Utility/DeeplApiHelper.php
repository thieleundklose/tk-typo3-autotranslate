<?php
declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Utility;

use DeepL\Translator;
use DeepL\AuthorizationException;
use DeepL\DeepLException;
use DeepL\TooManyRequestsException;
use DeepL\TranslatorOptions;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class DeeplApiHelper
{
    /**
     * Cache validation results per request to avoid hitting DeepL for every record.
     *
     * @var array<string,array>
     */
    private static array $apiKeyCheckCache = [];

    public static function createTranslator(?string $apiKey): Translator
    {
        return self::configuredTranslator() ?? new Translator((string)$apiKey, self::translatorOptions());
    }

    /**
     * Whether the extension setting translatorClass replaces the DeepL client.
     *
     * A misconfigured setting counts as set, so that checkApiKey() reports it
     * instead of falling back to DeepL.
     */
    public static function hasConfiguredTranslator(): bool
    {
        try {
            return self::configuredTranslatorClass() !== null;
        } catch (\Throwable $e) {
            return true;
        }
    }

    /**
     * The translator the extension setting translatorClass names, resolved
     * from the dependency injection container, or null when the setting is
     * empty. The class must extend \DeepL\Translator and be a public service.
     * It translates instead of the DeepL client, with its own access to its
     * provider, so the DeepL API key is not used.
     */
    private static function configuredTranslator(): ?Translator
    {
        $translatorClass = self::configuredTranslatorClass();
        if ($translatorClass === null) {
            return null;
        }

        $translator = GeneralUtility::getContainer()->get($translatorClass);
        if (!$translator instanceof Translator) {
            throw new \RuntimeException(sprintf(
                'The service "%s" named in the autotranslate setting translatorClass is not a \\DeepL\\Translator.',
                $translatorClass
            ), 1794326401);
        }

        return $translator;
    }

    private static function configuredTranslatorClass(): ?string
    {
        $translatorClass = $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['autotranslate']['translatorClass'] ?? null;
        if (!is_string($translatorClass) || trim($translatorClass) === '') {
            return null;
        }

        $translatorClass = ltrim(trim($translatorClass), '\\');
        if (!is_a($translatorClass, Translator::class, true)) {
            throw new \InvalidArgumentException(sprintf(
                'The autotranslate setting translatorClass names "%s", which is not a class extending \\DeepL\\Translator.',
                $translatorClass
            ), 1794326402);
        }

        return $translatorClass;
    }

    /**
     * Prüft, ob der DeepL API-Key gültig ist und gibt optional die Usage zurück.
     *
     * @param ?string $apiKey
     * @return array ['isValid' => bool, 'usage' => \DeepL\Usage|null, 'error' => string|null]
     */
    public static function checkApiKey(?string $apiKey): array
    {
        if (self::hasConfiguredTranslator()) {
            try {
                self::configuredTranslator();
            } catch (\Throwable $e) {
                return [
                    'isValid' => false,
                    'usage' => null,
                    'charactersLeft' => 0,
                    'error' => 'Translator class error: ' . $e->getMessage(),
                    'warning' => null,
                ];
            }

            // The configured translator manages its own provider access.
            return [
                'isValid' => true,
                'usage' => null,
                'charactersLeft' => null,
                'error' => null,
                'warning' => null,
            ];
        }

        if (!$apiKey) {
            return [
                'isValid' => false,
                'usage' => null,
                'charactersLeft' => 0,
                'error' => null,
                'warning' => null,
            ];
        }

        if (isset(self::$apiKeyCheckCache[$apiKey])) {
            return self::$apiKeyCheckCache[$apiKey];
        }

        try {
            $translator = self::createTranslator($apiKey);
            $usage = $translator->getUsage();
            $charactersLeft = null;
            if (is_object($usage) && isset($usage->character)) {
                $charactersLeft = $usage->character->limit - $usage->character->count;
            }
            return self::$apiKeyCheckCache[$apiKey] = [
                'isValid' => true,
                'usage' => $usage,
                'charactersLeft' => $charactersLeft,
                'error' => null,
                'warning' => null,
            ];
        } catch (AuthorizationException $e) {
            return self::$apiKeyCheckCache[$apiKey] = [
                'isValid' => false,
                'usage' => null,
                'charactersLeft' => 0,
                'error' => $e->getMessage(),
                'warning' => null,
            ];
        } catch (TooManyRequestsException $e) {
            return self::$apiKeyCheckCache[$apiKey] = [
                'isValid' => true,
                'usage' => null,
                'charactersLeft' => null,
                'error' => null,
                'warning' => 'DeepL is temporarily rate limiting requests: ' . $e->getMessage(),
            ];
        } catch (DeepLException $e) {
            return self::$apiKeyCheckCache[$apiKey] = [
                'isValid' => false,
                'usage' => null,
                'charactersLeft' => 0,
                'error' => 'DeepL error: ' . $e->getMessage(),
                'warning' => null,
            ];
        } catch (\Throwable $e) {
            return self::$apiKeyCheckCache[$apiKey] = [
                'isValid' => false,
                'usage' => null,
                'charactersLeft' => 0,
                'error' => 'Unexpected error: ' . $e->getMessage(),
                'warning' => null,
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
        if (!in_array($type, ['source', 'target'], true)) {
            throw new \InvalidArgumentException(sprintf('Invalid type "%s". Allowed values are "source" or "target".', $type));
        }
        try {
            $translatorClass = self::configuredTranslatorClass() ?? '';
        } catch (\Throwable $e) {
            return [];
        }
        $cacheIdentifier = 'deepl_' . $type . '_languages_' . md5($apiKey . '|' . $translatorClass);

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
            $translator = self::createTranslator($apiKey);
            if ($type === 'source') {
                $languages = $translator->getSourceLanguages();
            } else {
                $languages = $translator->getTargetLanguages();
            }
            $result = [];
            foreach ($languages as $language) {
                $languageData = get_object_vars($language);
                $result[] = [
                    (string)($languageData['name'] ?? ''),
                    (string)($languageData['code'] ?? ''),
                ];
            }
            if ($cache) {
                $cache->set($cacheIdentifier, $result, [], 86400 * 7); // 7 days cache-lifetime
            }
            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    private static function translatorOptions(): array
    {
        $proxy = self::resolveProxy();
        if ($proxy === null) {
            return [];
        }

        return [
            TranslatorOptions::PROXY => $proxy,
        ];
    }

    private static function resolveProxy(): ?string
    {
        $proxy = $GLOBALS['TYPO3_CONF_VARS']['HTTP']['proxy'] ?? null;

        if (is_string($proxy)) {
            $proxy = trim($proxy);
            return $proxy !== '' ? $proxy : null;
        }

        if (is_array($proxy)) {
            foreach (['https', 'http'] as $scheme) {
                if (isset($proxy[$scheme]) && is_string($proxy[$scheme])) {
                    $schemeProxy = trim($proxy[$scheme]);
                    if ($schemeProxy !== '') {
                        return $schemeProxy;
                    }
                }
            }
        }

        return null;
    }
}
