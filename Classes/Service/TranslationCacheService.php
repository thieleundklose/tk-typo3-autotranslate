<?php
declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Service;

use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use DeepL\TextResult;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Core\Environment;

final class TranslationCacheService
{
    private ?FrontendInterface $cache = null;

    public function __construct()
    {
        try {
            $caching = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('autotranslate', 'caching');
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
        foreach ($textResults as $index => $result) {
            if ($result instanceof TextResult) {
                $serialized[$index] = [
                    'text' => $result->text,
                    'detected_source_lang' => $result->detectedSourceLang ?? null,
                    'billed_characters' => $result->billedCharacters ?? null
                ];
            } else {
                // Preserve array structure - store null or invalid entries
                $serialized[$index] = null;
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
        foreach ($cached as $index => $data) {
            if ($data === null) {
                // Preserve null values to maintain array structure
                $results[$index] = null;
                continue;
            }
            
            // Create TextResult-like object (since TextResult constructor is protected)
            $result = new class($data['text'], $data['detected_source_lang'], $data['billed_characters']) {
                public function __construct(
                    public string $text,
                    public ?string $detectedSourceLang,
                    public ?int $billedCharacters,
                ) {}
            };
            $results[$index] = $result;
        }
        return $results;
    }

    /**
     * Clear all cached translations
     */
    public function clearCache(): bool
    {
        if ($this->cache === null) {
            return false; // Cache disabled - nothing to clear
        }

        try {
            $this->cache->flush();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

   /**
     * Get number of cached translation entries
     */
    public function getCacheEntryCount(): int
    {
        if ($this->cache === null) {
            return 0; // Cache disabled - no entries
        }

        try {
            // Get cache backend to access raw cache data
            $backend = $this->cache->getBackend();

            // Check if backend supports tagging (most modern backends do)
            if ($backend instanceof \TYPO3\CMS\Core\Cache\Backend\TaggableBackendInterface) {
                $identifiers = $backend->findIdentifiersByTag('autotranslate');
                return count($identifiers);
            }

            // Fallback for FileBackend: count cache files
            if ($backend instanceof \TYPO3\CMS\Core\Cache\Backend\FileBackend) {
                $cacheDirectory = $backend->getCacheDirectory();
                if (is_dir($cacheDirectory)) {
                    $iterator = new \RecursiveIteratorIterator(
                        new \RecursiveDirectoryIterator($cacheDirectory, \RecursiveDirectoryIterator::SKIP_DOTS),
                        \RecursiveIteratorIterator::LEAVES_ONLY
                    );

                    $count = 0;
                    foreach ($iterator as $file) {
                        if ($file->isFile() && $file->getExtension() === 'cache') {
                            $count++;
                        }
                    }
                    return $count;
                }
            }

            return 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get cache statistics
     */
    public function getCacheStatistics(): array
    {
        if ($this->cache === null) {
            return [
                'enabled' => false,
                'entries' => 0,
                'backend' => null,
                'size' => 0,
                'size_formatted' => '0 B'
            ];
        }

        $backend = $this->cache->getBackend();
        $entryCount = $this->getCacheEntryCount();
        $cacheSize = $this->calculateCacheSize($backend);

        return [
            'enabled' => true,
            'entries' => $entryCount,
            'backend' => get_class($backend),
            'size' => $cacheSize,
            'size_formatted' => $this->formatBytes($cacheSize)
        ];
    }

    /**
     * Calculate cache size for different backends
     */
    private function calculateCacheSize(\TYPO3\CMS\Core\Cache\Backend\BackendInterface $backend): int
    {
        $cacheSize = 0;

        try {
            if ($backend instanceof \TYPO3\CMS\Core\Cache\Backend\FileBackend) {
                // Try different approaches to get cache directory
                $cacheSize = $this->getFileBackendSize($backend);
            } elseif ($backend instanceof \TYPO3\CMS\Core\Cache\Backend\SimpleFileBackend) {
                $cacheSize = $this->getSimpleFileBackendSize($backend);
            }
            // For other backends (Redis, Database), size calculation is more complex
            // and might not be easily available
        } catch (\Exception $e) {
            // Fallback: estimate size based on entry count
            $cacheSize = $this->getCacheEntryCount() * 1024; // Rough estimate: 1KB per entry
        }

        return $cacheSize;
    }

    /**
     * Get size for FileBackend
     */
    private function getFileBackendSize(\TYPO3\CMS\Core\Cache\Backend\FileBackend $backend): int
    {
        $cacheSize = 0;

        try {
            // Try to access cache directory via reflection if getCacheDirectory is not public
            $reflection = new \ReflectionClass($backend);

            if ($reflection->hasMethod('getCacheDirectory') && $reflection->getMethod('getCacheDirectory')->isPublic()) {
                $cacheDirectory = $backend->getCacheDirectory();
            } elseif ($reflection->hasProperty('cacheDirectory')) {
                $property = $reflection->getProperty('cacheDirectory');
                $cacheDirectory = $property->getValue($backend);
            } else {
                // Fallback: construct expected cache directory path
                $cacheDirectory = Environment::getVarPath() . '/cache/data/autotranslate_cache/';
            }

            if (is_dir($cacheDirectory)) {
                $cacheSize = $this->calculateDirectorySize($cacheDirectory);
            }
        } catch (\Exception $e) {
            // If reflection fails, estimate based on entry count
            $cacheSize = $this->getCacheEntryCount() * 1024;
        }

        return $cacheSize;
    }

    /**
     * Get size for SimpleFileBackend
     */
    private function getSimpleFileBackendSize(\TYPO3\CMS\Core\Cache\Backend\SimpleFileBackend $backend): int
    {
        try {
            // Similar approach for SimpleFileBackend
            $reflection = new \ReflectionClass($backend);

            if ($reflection->hasProperty('cacheDirectory')) {
                $property = $reflection->getProperty('cacheDirectory');
                $cacheDirectory = $property->getValue($backend);

                if (is_dir($cacheDirectory)) {
                    return $this->calculateDirectorySize($cacheDirectory);
                }
            }
        } catch (\Exception $e) {
            // Fallback
        }

        return $this->getCacheEntryCount() * 1024;
    }

    /**
     * Calculate total size of directory
     */
    private function calculateDirectorySize(string $directory): int
    {
        $size = 0;

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $size += $file->getSize(); // @extensionScannerIgnoreLine
                }
            }
        } catch (\Exception $e) {
            // Directory not accessible or other error
            $size = 0;
        }

        return $size;
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }

}