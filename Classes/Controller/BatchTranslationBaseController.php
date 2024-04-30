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
     * get batch translation data
     * @return array
     */
    public function getBatchTranslationData(): array
    {
        $levels = (int)$this->getBackendUserAuthentication()->getSessionData('autotranslate.levels');
        $batchItems = $this->batchItemRepository->findAll();
        $batchItemsRecursive = $this->batchItemRepository->findAllRecursive($levels);

        $data = [
            'levels' => $levels,
            'batchItems' => $batchItems,
            'batchItemsRecursive' => $batchItemsRecursive,
            'pageUid' => $this->pageUid,
            'queryParams' =>  $this->queryParams
        ];

        // define moduleName for legacy version
        if ($this->typo3Version->getMajorVersion() < 12) {
            $data['moduleName'] = str_replace(['/module/', '/'], ['', '_'], $this->queryParams['route']);
        }

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

        parent::initializeAction();
    }

}
