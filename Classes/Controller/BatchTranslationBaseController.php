<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Controller;

use DateTime;
use DateTimeZone;
use Exception;
use ThieleUndKlose\Autotranslate\Domain\Model\BatchItem;
use ThieleUndKlose\Autotranslate\Domain\Repository\BatchItemRepository;
use ThieleUndKlose\Autotranslate\Domain\Repository\LogRepository;
use ThieleUndKlose\Autotranslate\Service\BatchTranslationService;
use ThieleUndKlose\Autotranslate\Service\TranslationCacheService;
use ThieleUndKlose\Autotranslate\Utility\DeeplApiHelper;
use ThieleUndKlose\Autotranslate\Utility\LogUtility;
use ThieleUndKlose\Autotranslate\Utility\PageUtility;
use ThieleUndKlose\Autotranslate\Utility\TranslationHelper;
use ThieleUndKlose\Autotranslate\Utility\Translator;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

/**
 * Base controller for batch translation backend module
 */
class BatchTranslationBaseController extends ActionController
{
    protected const MODULE_NAME = 'web_autotranslate';
    protected const MENU_LEVEL_ITEMS = [0, 1, 2, 3, 4, 250];

    protected ?TranslationCacheService $translationCacheService = null;
    protected ?PersistenceManager $persistenceManager = null;
    protected ?BatchTranslationService $batchTranslationService = null;
    protected ?BatchItemRepository $batchItemRepository = null;
    protected ?LogRepository $logRepository = null;

    protected array $queryParams = [];
    protected int $pageUid = 0;
    protected int $levels = 0;
    protected string $moduleName = self::MODULE_NAME;
    protected array $deeplApiKeyDetails = [];

    // =========================================================================
    // Dependency Injection
    // =========================================================================

    public function injectTranslationCacheService(TranslationCacheService $service): void
    {
        $this->translationCacheService = $service;
    }

    public function injectPersistenceManager(PersistenceManager $manager): void
    {
        $this->persistenceManager = $manager;
    }

    public function injectBatchTranslationService(BatchTranslationService $service): void
    {
        $this->batchTranslationService = $service;
    }

    public function injectBatchItemRepository(BatchItemRepository $repository): void
    {
        $this->batchItemRepository = $repository;
    }

    public function injectLogRepository(LogRepository $repository): void
    {
        $this->logRepository = $repository;
    }

    // =========================================================================
    // Initialization
    // =========================================================================

    public function getLogData(): array
    {
        $this->handleLogActions();

        $pageRecord = BackendUtility::getRecordWSOL('pages', $this->pageUid);
        $logs = $this->processLogs($this->logRepository->findAll());

        return [
            'pageUid' => $this->pageUid,
            'levels' => $this->levels,
            'pageTitle' => $pageRecord['title'] ?? '',
            'moduleName' => $this->moduleName,
            'logItemsCount' => $this->logRepository->countAll(),
            'logsGroupedByRequestId' => $this->groupLogsByRequestId($logs),
        ];
    }

    protected function handleLogActions(): void
    {
        $shouldReload = false;

        if ($this->request->hasArgument('delete')) {
            $requestIds = GeneralUtility::trimExplode(',', $this->request->getArgument('delete'));
            foreach ($requestIds as $requestId) {
                $this->logRepository->deleteByRequestId($requestId);
            }
            $this->showSuccess('Successfully deleted', sprintf('%d log entries were deleted.', count($requestIds)));
            $shouldReload = true;
        }

        if ($this->request->hasArgument('deleteAll')) {
            $this->logRepository->deleteAll();
            $this->showSuccess('Successfully deleted', 'All log entries were deleted.');
            $shouldReload = true;
        }

        if ($shouldReload) {
            $this->persistAndReload();
        }
    }

    private function showSuccess(string $title, string $message): void
    {
        $this->addFlashMessage($title, $message, ContextualFeedbackSeverity::OK);
    }

    // =========================================================================
    // Data Retrieval
    // =========================================================================

    private function persistAndReload(): void
    {
        $this->persistenceManager->persistAll();
        $this->reloadPage();
    }

    protected function reloadPage(): void
    {
        $this->redirectToPage($this->pageUid);
    }

    /**
     * @throws PropagateResponseException
     */
    protected function redirectToPage(int $pageUid): never
    {
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        $uri = (string)$uriBuilder->buildUriFromRoute($this->moduleName, [
            'id' => $pageUid,
            'action' => $this->request->getControllerActionName(),
        ]);

        throw new PropagateResponseException(
            new RedirectResponse($uri, 303),
            1738900000
        );
    }

    // =========================================================================
    // Action Handlers
    // =========================================================================

    private function processLogs(array $logs): array
    {
        return array_map(function (array $log): array {
            $log['time_seconds'] = (int)($log['time_micro'] ?? 0);
            $log['dataDecoded'] = $this->decodeLogData($log['data'] ?? '');
            $log['parsed_message'] = LogUtility::interpolate($log['message'] ?? '', $log['dataDecoded']);
            $log['formattedDate'] = $this->formatLogDate($log['time_micro'] ?? null);
            return $log;
        }, $logs);
    }

    private function decodeLogData(string $data): array
    {
        if (empty($data)) {
            return [];
        }

        $decoded = json_decode($data, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function formatLogDate(?float $timeMicro): string
    {
        if (!$timeMicro) {
            return '';
        }

        $dateTime = DateTime::createFromFormat('U.u', sprintf('%.6f', $timeMicro));
        return $dateTime ? $dateTime->format('Y-m-d H:i:s.u') : '';
    }

    private function groupLogsByRequestId(array $logs): array
    {
        $grouped = [];

        foreach ($logs as $log) {
            $requestId = $log['request_id'] ?? 'unknown';
            $grouped[$requestId][] = $log;
        }

        return $grouped;
    }

    public function getBatchTranslationData(): array
    {
        $this->handleBatchActions();

        if ($this->pageUid === 0) {
            return [];
        }

        $site = $this->getSiteConfiguration();
        if (!$site) {
            return ['moduleName' => $this->moduleName];
        }

        $languages = $this->getAccessibleLanguages($site);
        $batchItems = $this->getAccessibleBatchItems($languages);
        $pageRecord = BackendUtility::getRecordWSOL('pages', $this->pageUid);

        return [
            'moduleName' => $this->moduleName,
            'rootPageId' => $site->getRootPageId(),
            'batchItems' => $this->batchItemRepository->findAll(),
            'batchItemsRecursive' => $batchItems,
            'pageUid' => $this->pageUid,
            'levels' => $this->levels,
            'queryParams' => $this->queryParams,
            'pageTitle' => $pageRecord['title'] ?? '',
            'createForm' => $this->buildCreateFormData($languages, $pageRecord),
        ];
    }

    protected function handleBatchActions(): void
    {
        $shouldReload = false;

        if ($this->request->hasArgument('clearCache')) {
            $shouldReload = $this->handleClearCache();
        }

        if ($this->request->hasArgument('delete')) {
            $shouldReload = $this->handleDelete() || $shouldReload;
        }

        if ($this->request->hasArgument('execute')) {
            $this->handleExecute();
        }

        if ($this->request->hasArgument('reset')) {
            $this->handleReset();
        }

        if ($shouldReload) {
            $this->persistAndReload();
        }

        $this->showCacheInfo();
    }

    // =========================================================================
    // Batch Item Creation
    // =========================================================================

    private function handleClearCache(): bool
    {
        $cleared = $this->translationCacheService->clearCache();

        if ($cleared) {
            $this->showSuccess('Cache cleared successfully', 'Translation cache has been emptied.');
        } else {
            $this->showError('Failed to clear cache', 'Translation cache could not be cleared.');
        }

        return true;
    }

    private function showError(string $title, string $message): void
    {
        $this->addFlashMessage($title, $message, ContextualFeedbackSeverity::ERROR);
    }

    private function handleDelete(): bool
    {
        $items = $this->getBatchItemsFromArgument('delete');

        foreach ($items as $item) {
            $this->batchItemRepository->remove($item);
            $this->showSuccess('Successfully deleted', sprintf('Item with uid %d was deleted.', $item->getUid()));
        }

        return count($items) > 0;
    }

    // =========================================================================
    // Flash Messages
    // =========================================================================

    private function getBatchItemsFromArgument(string $argument): array
    {
        if (!$this->request->hasArgument($argument)) {
            return [];
        }

        $uids = GeneralUtility::trimExplode(',', $this->request->getArgument($argument));

        return array_filter(
            array_map(
                fn($uid) => $this->batchItemRepository->findByUid((int)$uid),
                $uids
            ),
            fn($item) => $item instanceof BatchItem
        );
    }

    private function handleExecute(): void
    {
        try {
            $items = $this->getBatchItemsFromArgument('execute');

            foreach ($items as $item) {
                if (!$item->isExecutable()) {
                    $this->showError(
                        'Item cannot be translated',
                        sprintf('Item with uid %d could not be translated. Check the error and reset it.', $item->getUid())
                    );
                    continue;
                }

                $success = $this->batchTranslationService->translate($item);

                if ($success) {
                    $item->markAsTranslated();
                    $this->showSuccess('Successfully translated', sprintf('Item with uid %d was translated.', $item->getUid()));
                } else {
                    $this->showError('Error while translating', sprintf('Item with uid %d could not be translated.', $item->getUid()));
                }

                $this->batchItemRepository->update($item);
            }
        } catch (Exception $e) {
            $this->showError('Error during translation', 'An error occurred: ' . $e->getMessage());
        }
    }

    private function handleReset(): void
    {
        $items = $this->getBatchItemsFromArgument('reset');

        foreach ($items as $item) {
            $item->setTranslated(null);
            $item->setError('');
            $this->batchItemRepository->update($item);
            $this->showSuccess('Reset successful', sprintf('Item with uid %d was reset.', $item->getUid()));
        }
    }

    protected function showCacheInfo(): void
    {
        $stats = $this->translationCacheService->getCacheStatistics();

        if (!$stats['enabled']) {
            $this->showInfo('Translation Cache: Disabled', 'All translations will use the API directly.');
            return;
        }

        $info = sprintf('Entries: %d | Size: %s', $stats['entries'], $stats['size_formatted']);
        $this->showInfo('Translation Cache: Active', $info);
    }

    private function showInfo(string $title, string $message): void
    {
        $this->addFlashMessage($title, $message, ContextualFeedbackSeverity::INFO);
    }

    private function getSiteConfiguration(): ?\TYPO3\CMS\Core\Site\Entity\Site
    {
        try {
            $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
            return $siteFinder->getSiteByPageId($this->pageUid);
        } catch (Exception) {
            $this->showWarning(
                'No site configuration found',
                'Please select a configured page or create a new site configuration.'
            );
            return null;
        }
    }

    private function showWarning(string $title, string $message): void
    {
        $this->addFlashMessage($title, $message, ContextualFeedbackSeverity::WARNING);
    }

    // =========================================================================
    // Navigation
    // =========================================================================

    private function getAccessibleLanguages(\TYPO3\CMS\Core\Site\Entity\Site $site): array
    {
        $languages = TranslationHelper::possibleTranslationLanguages($site->getLanguages());
        $backendUser = $this->getBackendUser();

        $filtered = array_filter(
            $languages,
            // @extensionScannerIgnoreLine
            fn($lang) => $backendUser->checkLanguageAccess($lang->getLanguageId())
        );

        if (empty($filtered)) {
            $this->showWarning('No target language available', 'Please choose another page or contact the administrator.');
        }

        return $filtered;
    }

    private function getAccessibleBatchItems(array $languages): array
    {
        $pageId = (int)($this->request->hasArgument('id') ? $this->request->getArgument('id') : 0);
        $items = $this->batchItemRepository->findAllRecursive($this->levels, $pageId);
        $backendUser = $this->getBackendUser();

        return array_filter(
            $items->toArray(),
            function (BatchItem $item) use ($backendUser, $languages) {
                $pageRecord = BackendUtility::getRecordWSOL('pages', $item->getPid());
                return isset($languages[$item->getSysLanguageUid()])
                    && $backendUser->doesUserHaveAccess($pageRecord, Permission::CONTENT_EDIT);
            }
        );
    }

    private function buildCreateFormData(array $languages, ?array $pageRecord): array
    {
        $backendUser = $this->getBackendUser();
        $batchItem = null;

        if ($pageRecord && $backendUser->doesUserHaveAccess($pageRecord, Permission::CONTENT_EDIT)) {
            $batchItem = new BatchItem();
            $batchItem->setPid($this->pageUid);
            $batchItem->setTranslate(new DateTime());
        } else {
            $this->showWarning('No translations available', 'Please choose another page or contact the administrator.');
        }

        return [
            'pages' => $batchItem ? [$batchItem->getPid() => $batchItem->getPageTitle()] : null,
            'recursive' => $this->translateMenuLevelItems(),
            'priority' => $this->translatePriorityOptions(),
            'targetLanguage' => array_map(fn($lang) => $lang->getTitle(), $languages),
            'mode' => $this->translateModeOptions(),
            'frequency' => $this->translateFrequencyOptions(),
            'redirectAction' => $this->request->getControllerActionName(),
            'batchItem' => $batchItem,
        ];
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    private function translateMenuLevelItems(): array
    {
        $lang = $this->getLanguageService();
        $result = [];

        foreach (self::MENU_LEVEL_ITEMS as $level) {
            $result[$level] = $lang->sL("LLL:EXT:autotranslate/Resources/Private/Language/locallang_mod.xlf:mlang_labels_menu_level.{$level}");
        }

        return $result;
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }

    private function translatePriorityOptions(): array
    {
        return $this->translateOptions([
            BatchItem::PRIORITY_LOW,
            BatchItem::PRIORITY_MEDIUM,
            BatchItem::PRIORITY_HIGH,
        ], 'autotranslate_batch.priority.');
    }

    private function translateOptions(array $keys, string $prefix): array
    {
        $lang = $this->getLanguageService();
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $lang->sL("LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:{$prefix}{$key}");
        }

        return $result;
    }

    private function translateModeOptions(): array
    {
        return $this->translateOptions([
            Translator::TRANSLATE_MODE_BOTH,
            Translator::TRANSLATE_MODE_UPDATE_ONLY,
        ], 'autotranslate_batch.mode.');
    }

    private function translateFrequencyOptions(): array
    {
        return $this->translateOptions([
            BatchItem::FREQUENCY_ONCE,
            BatchItem::FREQUENCY_WEEKLY,
            BatchItem::FREQUENCY_DAILY,
            BatchItem::FREQUENCY_RECURRING,
        ], 'autotranslate_batch.frequency.');
    }

    protected function initializeAction(): void
    {
        $this->queryParams = array_merge_recursive(
            $this->request->getQueryParams(),
            $this->request->getParsedBody() ?? []
        );

        $this->pageUid = (int)($this->queryParams['id'] ?? 0);
        $this->levels = $this->loadLevelsFromSession();

        if (isset($this->queryParams['levels'])) {
            $this->levels = (int)$this->queryParams['levels'];
            $this->saveLevelsToSession($this->levels);
        }

        parent::initializeAction();
    }

    private function loadLevelsFromSession(): int
    {
        return $this->getBackendUser()->getSessionData('autotranslate.levels') ?? 0;
    }

    protected function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    private function saveLevelsToSession(int $levels): void
    {
        $this->getBackendUser()->setAndSaveSessionData('autotranslate.levels', $levels);
    }

    protected function getCommonTemplateVariables(array $data = []): array
    {
        $cacheStats = $this->translationCacheService->getCacheStatistics();

        return array_merge($data, [
            'cacheEnabled' => $cacheStats['enabled'],
            'cacheStats' => $cacheStats,
            'pageUid' => $this->pageUid,
            'moduleName' => $this->moduleName,
        ]);
    }

    protected function createActionAbstract(BatchItem $batchItem, int $levels): void
    {
        $this->adjustTimezoneOffset($batchItem);
        $this->batchItemRepository->add($batchItem);

        $createdCount = 1 + $this->createSubpageItems($batchItem, $levels);

        $this->showSuccess(
            'Queue items created',
            sprintf('%d items created for page with uid %d.', $createdCount, $this->pageUid)
        );
    }

    private function adjustTimezoneOffset(BatchItem $batchItem): void
    {
        $context = GeneralUtility::makeInstance(Context::class);
        $timezone = new DateTimeZone($context->getPropertyFromAspect('date', 'timezone'));
        $offset = $timezone->getOffset(new DateTime('now'));

        if ($offset !== 0) {
            $translateTime = $batchItem->getTranslate();
            $translateTime->modify("-{$offset} seconds");
            $batchItem->setTranslate($translateTime);
        }
    }

    private function createSubpageItems(BatchItem $batchItem, int $levels): int
    {
        if ($levels <= 0) {
            return 0;
        }

        $count = 0;
        $subPages = PageUtility::getSubpageIds($this->pageUid, $levels - 1);
        $backendUser = $this->getBackendUser();

        foreach ($subPages as $subPageUid) {
            $pageRecord = BackendUtility::getRecordWSOL('pages', $subPageUid);

            if (!$backendUser->doesUserHaveAccess($pageRecord, Permission::CONTENT_EDIT)) {
                continue;
            }

            $newItem = clone $batchItem;
            $newItem->setPid($subPageUid);
            $this->batchItemRepository->add($newItem);
            $count++;
        }

        return $count;
    }

    protected function addDeeplApiKeyInfoMessage(): void
    {
        $apiKeyDetails = TranslationHelper::apiKey($this->pageUid);
        $apiKey = $apiKeyDetails['key'] ?? null;
        $this->deeplApiKeyDetails = DeeplApiHelper::checkApiKey($apiKey);

        $maskedKey = $this->maskApiKey($apiKey);
        $messages = [];
        $severity = ContextualFeedbackSeverity::INFO;

        if ($this->deeplApiKeyDetails['usage']) {
            $usage = (string)$this->deeplApiKeyDetails['usage'];
            $usage = str_replace([PHP_EOL, 'Characters: '], [' ', ''], $usage);
            $messages[] = trim($usage) . ' Characters';
        }

        if ($this->deeplApiKeyDetails['error']) {
            $messages[] = $this->deeplApiKeyDetails['error'];
            $severity = ContextualFeedbackSeverity::ERROR;
        }

        if (!empty($messages)) {
            $this->addFlashMessage('DeepL API Key: ' . $maskedKey, implode(PHP_EOL, $messages), $severity);
        }
    }

    private function maskApiKey(?string $apiKey): string
    {
        if (!$apiKey) {
            return '(not set)';
        }

        $masked = '';
        $hiddenCount = 0;

        foreach (str_split($apiKey) as $char) {
            if ($char === '-') {
                $masked .= '-';
            } elseif ($hiddenCount < 20) {
                $masked .= '*';
                $hiddenCount++;
            } else {
                $masked .= $char;
            }
        }

        return $masked;
    }
}
