<?php
declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Service;

use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use DeepL\TextResult;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

class TranslationCacheService
{
    private ?FrontendInterface $cache = null;

    public function __construct()
    {
        try {
            $caching = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('autotranslate', 'caching');
            // echo $caching; die;

        } catch (\Exception $e) {
            $caching = false;
        }

        if ($caching) {
            $this->cache = GeneralUtility::makeInstance(CacheManager::class)->getCache('autotranslate_cache');
        } else {
            $this->cache = null;
        }
    }

    /**
     * Generates cache key for translation request
     */
    public function generateCacheKey(array $toTranslate, ?string $sourceLang, string $targetLang, array $options): string
    {
        $cacheData = [
            'texts' => $toTranslate,
            'source' => $sourceLang,
            'target' => $targetLang,
            'options' => $this->normalizeOptions($options)
        ];

        return 'translation_' . md5(serialize($cacheData));
    }

    /**
     * Get cached translation results
     */
    public function getCachedTranslation(string $cacheKey): ?array
    {
        if ($this->cache === null || !$this->cache->has($cacheKey)) {
            return null;
        }

        $cached = $this->cache->get($cacheKey);
        return $this->unserializeTextResults($cached);
    }

    /**
     * Cache translation results
     */
    public function setCachedTranslation(string $cacheKey, array $textResults, int $lifetime = 86400): void
    {
        if ($this->cache === null) {
            return; // Cache disabled - do nothing
        }

        $serialized = $this->serializeTextResults($textResults);
        $this->cache->set($cacheKey, $serialized, ['autotranslate'], $lifetime);
    }

    /**
     * Get partial cache hits and uncached texts
     */
    public function getPartialCacheHits(array $toTranslate, ?string $sourceLang, string $targetLang, array $options): array
    {
        if ($this->cache === null) {
            // Cache disabled - all texts must be translated
            return [
                'cached' => [],
                'uncached' => $toTranslate,
                'mapping' => array_keys($toTranslate)
            ];
        }

        $cachedResults = [];
        $uncachedTexts = [];
        $indexMapping = [];

        foreach ($toTranslate as $index => $text) {
            $singleTextKey = $this->generateCacheKey([$text], $sourceLang, $targetLang, $options);

            if ($this->cache->has($singleTextKey)) {
                $cached = $this->getCachedTranslation($singleTextKey);
                if ($cached && isset($cached[0])) {
                    $cachedResults[$index] = $cached[0];
                } else {
                    $uncachedTexts[] = $text;
                    $indexMapping[count($uncachedTexts) - 1] = $index;
                }
            } else {
                $uncachedTexts[] = $text;
                $indexMapping[count($uncachedTexts) - 1] = $index;
            }
        }

        return [
            'cached' => $cachedResults,
            'uncached' => $uncachedTexts,
            'mapping' => $indexMapping
        ];
    }

    /**
     * Cache individual text translations
     */
    public function cacheIndividualTranslations(array $texts, array $results, ?string $sourceLang, string $targetLang, array $options): void
    {
        if ($this->cache === null) {
            return; // Cache disabled - do nothing
        }

        foreach ($texts as $index => $text) {
            if (isset($results[$index])) {
                $singleTextKey = $this->generateCacheKey([$text], $sourceLang, $targetLang, $options);
                $this->setCachedTranslation($singleTextKey, [$results[$index]]);
            }
        }
    }

    /**
     * Normalize options for consistent caching
     */
    private function normalizeOptions(array $options): array
    {
        // Remove volatile options that shouldn't affect caching
        $normalized = $options;
        unset($normalized['timeout']); // timeout shouldn't affect translation result

        // Sort to ensure consistent cache keys
        ksort($normalized);
        return $normalized;
    }

    /**
     * Serialize TextResult objects for caching
     */
    private function serializeTextResults(array $textResults): array
    {
        $serialized = [];
        foreach ($textResults as $result) {
            if ($result instanceof TextResult) {
                $serialized[] = [
                    'text' => $result->text,
                    'detected_source_lang' => $result->detectedSourceLang ?? null,
                    'billed_characters' => $result->billedCharacters ?? null
                ];
            }
        }
        return $serialized;
    }

    /**
     * Unserialize cached data back to TextResult objects
     */
    private function unserializeTextResults(array $cached): array
    {
        $results = [];
        foreach ($cached as $data) {
            // Create TextResult-like object (since TextResult constructor is protected)
            $result = new class($data['text'], $data['detected_source_lang']) {
                public $text;
                public $detectedSourceLang;
                public $billedCharacters;

                public function __construct(string $text, ?string $detectedSourceLang) {
                    $this->text = $text;
                    $this->detectedSourceLang = $detectedSourceLang;
                }
            };
            $result->billedCharacters = $data['billed_characters'];
            $results[] = $result;
        }
        return $results;
    }
}