<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Controller;

use Exception;
use ThieleUndKlose\Autotranslate\Domain\Model\BatchItem;
use ThieleUndKlose\Autotranslate\Domain\Repository\BatchItemRepository;
use ThieleUndKlose\Autotranslate\Service\BatchTranslationService;
use ThieleUndKlose\Autotranslate\Utility\PageUtility;
use ThieleUndKlose\Autotranslate\Utility\TranslationHelper;
use ThieleUndKlose\Autotranslate\Utility\Translator;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

/**
 * Class BatchTranslationBaseController for backend modules
 */
class BatchTranslationBaseController extends ActionController
{
    const MESSAGE_NOTICE = -2;
    const MESSAGE_INFO = -1;
    const MESSAGE_OK = 0;
    const MESSAGE_WARNING = 1;
    const MESSAGE_ERROR = 2;

    /**
     * @var PersistenceManager
     */
    protected $persistenceManager;

    /**
     * Inject the persistence manager
     *
     * @param PersistenceManager $persistenceManager
     */
    public function injectPersistenceManager(PersistenceManager $persistenceManager)
    {
        $this->persistenceManager = $persistenceManager;
    }

    /**
     * @var BatchTranslationService
     */
    protected $batchTranslationService;

    /**
     * @param BatchTranslationService $batchTranslationService
     * @return void
     */
    public function injectBatchTranslationService(BatchTranslationService $batchTranslationService): void
    {
        $this->batchTranslationService = $batchTranslationService;
    }

    /**
     * @var BatchItemRepository
     */
    protected $batchItemRepository;

    /**
     * @param BatchItemRepository $batchItemRepository
     * @return void
     */
    public function injectBatchItemRepository(BatchItemRepository $batchItemRepository): void
    {
        $this->batchItemRepository = $batchItemRepository;
    }

    /**
     * @var Typo3Version
     */
    protected $typo3Version;

    /**
     * @var Array
     */
    protected $queryParams;

    /**
     * @var integer
     */
    protected int $pageUid = 0;

    /**
     * @var integer
     */
    protected int $levels = 0;

    /**
     * default module name of backend module overwritten by legacy typo3 version module names
     */
    protected $moduleName = 'web_autotranslate';

    /**
     * levels for recursive menu
     * @var array
     */
    protected array $menuLevelItems = [0, 1, 2, 3, 4, 250];

    /**
     * get batch translation data
     * @return array
     */
    public function getBatchTranslationData(): array
    {
        $this->handleActionArguments();

        if ($this->pageUid === 0) {
            return [];
        }

        $data = [];

        if ($this->moduleName !== null) {
            $data['moduleName'] = $this->moduleName;
        }

        if ($this->typo3Version->getMajorVersion() < 12) {
            $pageId = (int)GeneralUtility::_GP('id');
        } else {
            $pageId = $this->request->hasArgument('id') ? (int)$this->request->getArgument('id') : 0;
        }

        $batchItems = $this->batchItemRepository->findAll();
        $batchItemsRecursive = $this->batchItemRepository->findAllRecursive(
            $this->levels,
            $pageId
        );

        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        try {
            $siteConfiguration = $siteFinder->getSiteByPageId($this->pageUid);
            $data['rootPageId'] = $siteConfiguration->getRootPageId();
        } catch(Exception $e) {

            $this->addMessage(
                'No site configuration found',
                'Please select a configured page first or create a new configuration for this page.',
                self::MESSAGE_WARNING
            );

        }

        $backendUser = $this->getBackendUserAuthentication();

        // Filter languages by users access rights
        $languages = isset($data['rootPageId']) ? TranslationHelper::possibleTranslationLanguages($siteConfiguration->getLanguages()) : [];
        $languages = array_filter($languages, fn($language) => $backendUser->checkLanguageAccess($language->getLanguageId()));

        if (empty($languages)) {
            $this->addMessage(
                'No target language available',
                'Please choose another page or contact the administrator.',
                self::MESSAGE_WARNING
            );
        }

        $typo3Version = GeneralUtility::makeInstance(Typo3Version::class);
        $majorVersion = $typo3Version->getMajorVersion();

        // Filter items by users access rights
        $batchItemsRecursive = array_filter($batchItemsRecursive->toArray(), function ($batchItem) use ($backendUser, $languages) {
            $rowBatchItem = BackendUtility::getRecordWSOL('pages', $batchItem->getPid());
            return isset($languages[$batchItem->getSysLanguageUid()]) && $backendUser->doesUserHaveAccess($rowBatchItem, Permission::CONTENT_EDIT);
        });

        $rowPage = BackendUtility::getRecordWSOL('pages', $this->pageUid);
        if ($backendUser->doesUserHaveAccess($rowPage, Permission::CONTENT_EDIT)) {
            $batchItem = new BatchItem();
            $batchItem->setPid($this->pageUid);
            $batchItem->setTranslate(new \DateTime());
        } else {
            $this->addMessage(
                'No translations available on selected page',
                'Please choose another page or contact the administrator.',
                self::MESSAGE_WARNING
            );
        }

        // merge modified params
        $data = array_merge(
            $data,
            [
                'batchItems' => $batchItems,
                'batchItemsRecursive' => $batchItemsRecursive,
                'pageUid' => $this->pageUid,
                'levels' => $this->levels,
                'queryParams' =>  $this->queryParams,
                'pageTitle' => $rowPage['title'],
                'createForm' => [
                    'pages' => isset($batchItem) ? [
                        $batchItem->getPid() => $batchItem->getPageTitle()
                    ] : null,
                    'recursive' => array_map(fn($item) => $this->getLanguageService()->sL('LLL:EXT:autotranslate/Resources/Private/Language/locallang_mod.xlf:mlang_labels_menu_level.' . $item), $this->menuLevelItems),
                    'priority' => [
                        BatchItem::PRIORITY_LOW => $this->getLanguageService()->sL('LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:autotranslate_batch.priority.' . BatchItem::PRIORITY_LOW),
                        BatchItem::PRIORITY_MEDIUM => $this->getLanguageService()->sL('LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:autotranslate_batch.priority.' . BatchItem::PRIORITY_MEDIUM),
                        BatchItem::PRIORITY_HIGH => $this->getLanguageService()->sL('LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:autotranslate_batch.priority.' . BatchItem::PRIORITY_HIGH),
                    ],
                    'targetLanguage' => array_map(fn($item) => $item->getTitle(), $languages),
                    'mode' => [
                        Translator::TRANSLATE_MODE_BOTH => $this->getLanguageService()->sL('LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:autotranslate_batch.mode.' . Translator::TRANSLATE_MODE_BOTH),
                        Translator::TRANSLATE_MODE_UPDATE_ONLY => $this->getLanguageService()->sL('LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:autotranslate_batch.mode.' . Translator::TRANSLATE_MODE_UPDATE_ONLY)
                    ],
                    'frequency' => [
                        BatchItem::FREQUENCY_ONCE => $this->getLanguageService()->sL('LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:autotranslate_batch.frequency.' . BatchItem::FREQUENCY_ONCE),
                        BatchItem::FREQUENCY_WEEKLY => $this->getLanguageService()->sL('LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:autotranslate_batch.frequency.' . BatchItem::FREQUENCY_WEEKLY),
                        BatchItem::FREQUENCY_DAILY => $this->getLanguageService()->sL('LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:autotranslate_batch.frequency.' . BatchItem::FREQUENCY_DAILY),
                        BatchItem::FREQUENCY_RECURRING => $this->getLanguageService()->sL('LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:autotranslate_batch.frequency.' . BatchItem::FREQUENCY_RECURRING),
                    ],
                    'redirectAction' => $this->request->getControllerActionName(),
                    'batchItem' =>  $batchItem ?? null,
                ],
                'typo3Version' => $majorVersion,
            ]
        );

        return $data;
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }

    protected function getBackendUserAuthentication(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    /**
     * Add batch translation items by form data to queue
     * @param BatchItem $batchItem
     * @param int $levels
     * @return void
     */
    protected function createActionAbstract(BatchItem $batchItem, int $levels): void
    {

        $context = GeneralUtility::makeInstance(Context::class);

        // calc offset
        $timezone = new \DateTimeZone($context->getPropertyFromAspect('date', 'timezone'));
        $datetime = new \DateTime('now');
        $offset = $timezone->getOffset($datetime);

        // modify time with offset
        if ($offset){
            $translateTime = $batchItem->getTranslate();
            $translateTime->modify("-{$offset} seconds");
            $batchItem->setTranslate($translateTime);
        }

        $this->batchItemRepository->add($batchItem);
        $counter = 1;

        if ($levels > 0)  {
            $subPages = PageUtility::getSubpageIds($this->pageUid, $levels - 1);
            $backendUser = $this->getBackendUserAuthentication();
            foreach ($subPages as $subPageUid) {
                $rowSubPage = BackendUtility::getRecordWSOL('pages', $subPageUid);
                if (!$backendUser->doesUserHaveAccess($rowSubPage, Permission::CONTENT_EDIT)) {
                    continue;
                }
                $counter++;
                $batchItem = clone $batchItem;
                $batchItem->setPid($subPageUid);
                $this->batchItemRepository->add($batchItem);
            }
        }

        $this->addMessage(
            'Queue items created',
            $counter . ' items created with given parameters for page with uid ' . $this->pageUid . '.',
        );
    }

    /**
     * Add a message to the flash message queue, overwritten by child controllers
     * @param string $title
     * @param string $message
     * @param int $severity
     * @return void
     */
    protected function addMessage(string $title, string $message, int $severity = self::MESSAGE_OK): void
    {
        $this->addFlashMessage(
            $message,
            $title,
            $this->typo3Version->getMajorVersion() > 11 ? $this->mapSeverity($severity) : $severity
        );
    }

    /**
     * Map old FlashMessage constants to new ContextualFeedbackSeverity constants.
     *
     * @param int $oldSeverity
     * @return ContextualFeedbackSeverity
     */
    private function mapSeverity(int $oldSeverity): ContextualFeedbackSeverity
    {
        switch ($oldSeverity) {
            case self::MESSAGE_NOTICE:
                return ContextualFeedbackSeverity::NOTICE;
            case self::MESSAGE_INFO:
                return ContextualFeedbackSeverity::INFO;
            case self::MESSAGE_OK:
                return ContextualFeedbackSeverity::OK;
            case self::MESSAGE_WARNING:
                return ContextualFeedbackSeverity::WARNING;
            case self::MESSAGE_ERROR:
                return ContextualFeedbackSeverity::ERROR;
            default:
                throw new \InvalidArgumentException('Invalid severity level');
        }
    }

    /**
     * Function will be called before every other action
     */
    protected function initializeAction(): void
    {
        $this->typo3Version = GeneralUtility::makeInstance(Typo3Version::class);

        $this->queryParams = array_merge_recursive($this->request->getQueryParams(), $this->request->getParsedBody() ?? []);

        if (isset($this->queryParams['id'])){
            $this->pageUid = (int)$this->queryParams['id'];
        }

        if ($this->typo3Version->getMajorVersion() < 12) {
            // define moduleName for legacy version
            $this->moduleName = str_replace(['/module/', '/'], ['', '_'], $this->queryParams['route']);

            // merge query params for legacy modules
            $moduleQueryKey = strtolower('tx_autotranslate_' . $this->moduleName);
            if (isset($this->queryParams[$moduleQueryKey])) {
                $this->queryParams = array_merge($this->queryParams, $this->queryParams[$moduleQueryKey]);
                unset($this->queryParams[$moduleQueryKey]);
            }
        }

        // get levels from session
        $levelsFromSession = $this->getBackendUserAuthentication()->getSessionData('autotranslate.levels');
        if ($levelsFromSession !== null) {
            $this->levels = $levelsFromSession;
        }

        // check query params for given levels and store it in session
        if (isset($this->queryParams['levels'])) {
            $this->levels = (int)$this->queryParams['levels'];
            $this->getBackendUserAuthentication()->setAndSaveSessionData('autotranslate.levels', $this->levels);
        }

        parent::initializeAction();
    }

    /**
     * Collect batch items from given argument
     *
     * @param string $argument
     * @return array
     */
    private function getBatchItemsFromArgument(string $argument): array
    {
        $items = [];
        if ($this->request->hasArgument($argument)) {
            $uids = GeneralUtility::trimExplode(',', $this->request->getArgument($argument));
            foreach ($uids as $uid) {
                $item = $this->batchItemRepository->findByUid((int)$uid);
                if ($item instanceof BatchItem) {
                    $items[] = $item;
                }
            }
        }
        return $items;

    }

    /**
     * Function to handle actions like delete, execute or other
     */
    protected function handleActionArguments()
    {
        $reload = false;

        if ($this->request->hasArgument('delete')) {
            $items = $this->getBatchItemsFromArgument('delete');
            foreach ($items as $item) {
                $this->batchItemRepository->remove($item);
                $this->addMessage(
                    'Successfully deleted',
                    sprintf('Item with uid %s was deleted.', $item->getUid()),
                    self::MESSAGE_OK
                );
                $reload = true;
            }
        }

        if ($this->request->hasArgument('execute')) {
            $items = $this->getBatchItemsFromArgument('execute');
            foreach ($items as $item) {
                if (!$item->isExecutable()) {
                    $this->addMessage(
                        'Item can not be translated',
                        sprintf('Item with uid %s could not be translated. Check the error and reset it.', $item->getUid()),
                        self::MESSAGE_ERROR
                    );
                    continue;
                }
                $res = $this->batchTranslationService->translate($item);
                if ($res === true) {
                    $item->markAsTranslated();
                    $this->addMessage(
                        'Successfully translated',
                        sprintf('Item with uid %s was translated.', $item->getUid()),
                        self::MESSAGE_OK
                    );
                } else {
                    $this->addMessage(
                        'Error while translating',
                        sprintf('Item with uid %s could not be translated.', $item->getUid()),
                        self::MESSAGE_ERROR
                    );
                }
                $this->batchItemRepository->update($item);
            }
        }

        if ($this->request->hasArgument('reset')) {
            $items = $this->getBatchItemsFromArgument('reset');
            foreach ($items as $item) {
                $item->setTranslated();
                $item->setError('');
                $this->addMessage(
                    'Reset successful',
                    sprintf('Translated date for item with uid %s was removed.', $item->getUid()),
                    self::MESSAGE_OK
                );
                $this->batchItemRepository->update($item);
            }
        }

        if ($reload) {
            $this->persistenceManager->persistAll();
            $this->reloadPage();
        }
    }

    /**
     * Reload the current page.
     *
     * @param int $pageUid
     */
    protected function reloadPage()
    {
        $this->redirectToPage($this->pageUid);
    }

    /**
     * Redirects to the specified page UID.
     *
     * @param int $pageUid
     */
    protected function redirectToPage(int $pageUid)
    {

        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        $uri = $uriBuilder->buildUriFromRoute($this->moduleName, ['id' => $pageUid]);

        header('Location: ' . $uri);
        exit;
    }

}
