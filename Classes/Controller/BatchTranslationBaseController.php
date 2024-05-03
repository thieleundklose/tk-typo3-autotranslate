<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Controller;

use ThieleUndKlose\Autotranslate\Domain\Model\BatchItem;
use ThieleUndKlose\Autotranslate\Domain\Repository\BatchItemRepository;
use ThieleUndKlose\Autotranslate\Utility\PageUtility;
use ThieleUndKlose\Autotranslate\Utility\TranslationHelper;
use ThieleUndKlose\Autotranslate\Utility\Translator;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

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
     * used for legacy version to set moduleName manually
     */
    protected $moduleName = null;

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
        if ($this->pageUid === 0) {
            return [];
        }

        $data = [];

        if ($this->moduleName !== null) {
            $data['moduleName'] = $this->moduleName;
        }

        $batchItems = $this->batchItemRepository->findAll();
        $batchItemsRecursive = $this->batchItemRepository->findAllRecursive($this->levels);


        $batchItem = new BatchItem();
        $batchItem->setPid($this->pageUid);
        $batchItem->setTranslate(new \DateTime());

        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $siteConfiguration = $siteFinder->getSiteByPageId($this->pageUid);
        $languages = TranslationHelper::possibleTranslationLanguages($siteConfiguration->getLanguages());

        // merge modified params
        $data = array_merge(
            $data,
            [
                'batchItems' => $batchItems,
                'batchItemsRecursive' => $batchItemsRecursive,
                'pageUid' => $this->pageUid,
                'levels' => $this->levels,
                'queryParams' =>  $this->queryParams,

                'createForm' => [
                    'pages' => [
                        $batchItem->getPid() => $batchItem->getPageTitle()
                    ],
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
                    'batchItem' => $batchItem,
                ],

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
        $this->batchItemRepository->add($batchItem);
        $counter = 1;

        if ($levels > 0)  {
            $subPages = PageUtility::getSubpageIds($batchItem->getPid(), $levels - 1);
            foreach ($subPages as $subPageUid) {
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
            $severity
        );
    }

    /**
     * Function will be called before every other action
     */
    protected function initializeAction()
    {
        $this->typo3Version = GeneralUtility::makeInstance(Typo3Version::class);

        if ($this->typo3Version->getMajorVersion() < 11) {
            $this->queryParams = array_merge_recursive($GLOBALS['_GET'], $GLOBALS['_POST'] ?? []);
        } else {
            $this->queryParams = array_merge_recursive($this->request->getQueryParams(), $this->request->getParsedBody() ?? []);
        }

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

        if ($this->arguments->hasArgument('batchItem')) {
            $this->arguments->getArgument('batchItem')->getPropertyMappingConfiguration()->forProperty('translate')->setTypeConverter(new \ThieleUndKlose\Autotranslate\Property\TypeConverter\DateTimeConverter());
        }

        parent::initializeAction();
    }

}
