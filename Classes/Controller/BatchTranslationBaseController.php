<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Controller;

use ThieleUndKlose\Autotranslate\Domain\Repository\BatchItemRepository;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

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

        return [
            'levels' => $levels,
            'batchItems' => $batchItems,
            'batchItemsRecursive' => $batchItemsRecursive,
        ];

    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }

    protected function getBackendUserAuthentication(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
    
    // protected function createMenu(): void {
    //     $menu = $view->getDocHeaderComponent()->getMenuRegistry()->makeMenu();
    // }
}
