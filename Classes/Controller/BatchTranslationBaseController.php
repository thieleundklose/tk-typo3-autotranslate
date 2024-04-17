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
     * get batch translation data
     * @return array
     */    
    public function getBatchTranslationData(): array
    {   
        $levels = 1;

        $batchItems = $this->batchItemRepository->findAll();
        $batchItemsRecursive = $this->batchItemRepository->findAllRecursive($levels);

        return [
            'levels' => $levels,
            'batchItems' => $batchItems,
            'batchItemsRecursive' => $batchItemsRecursive,
        ];

    }
}