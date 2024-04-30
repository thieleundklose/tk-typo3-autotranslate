<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Controller;

use ThieleUndKlose\Autotranslate\Domain\Repository\BatchItemRepository;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class BatchTranslationBaseController for backend modules
 */
class BatchTranslationBaseController extends ActionController
{

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
     * get batch translation data
     * @return array
     */
    public function getBatchTranslationData(): array
    {
        $data = [];


        if ($this->moduleName !== null) {
            $data['moduleName'] = $this->moduleName;
        }



        $batchItems = $this->batchItemRepository->findAll();
        $batchItemsRecursive = $this->batchItemRepository->findAllRecursive($this->levels);

        // merge modified params
        $data = array_merge(
            $data,
            [
                'batchItems' => $batchItems,
                'batchItemsRecursive' => $batchItemsRecursive,
                'pageUid' => $this->pageUid,
                'levels' => $this->levels,
                'queryParams' =>  $this->queryParams
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
     * Function will be called before every other action
     */
    protected function initializeAction()
    {
        $this->typo3Version = GeneralUtility::makeInstance(Typo3Version::class);

        if ($this->typo3Version->getMajorVersion() < 11) {
            $this->queryParams = $GLOBALS['_GET'];
        } else {
            $this->queryParams = $this->request->getQueryParams();
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

        parent::initializeAction();
    }

}
