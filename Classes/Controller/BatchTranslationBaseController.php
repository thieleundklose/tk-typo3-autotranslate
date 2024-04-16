<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Controller;

use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

/**
 * Class BatchTranslationBaseController for backend modules
 */
class BatchTranslationBaseController extends ActionController
{
    /**
     * Fill view data for backend module
     * @return void
     */    
    public function loadViewData()
    {   
        $levels = 1;

        $batchItems = $this->batchItemRepository->findAll();
        $batchItemsRecursive = $this->batchItemRepository->findAllRecursive($levels);

        $this->view->assignMultiple(
            [
                'levels' => $levels,
                'batchItems' => $batchItems,
                'batchItemsRecursive' => $batchItemsRecursive,
            ]
        );

    }
}