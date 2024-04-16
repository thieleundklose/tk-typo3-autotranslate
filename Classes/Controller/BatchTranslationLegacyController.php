<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Controller;

use ThieleUndKlose\Autotranslate\Domain\Repository\BatchItemRepository;

/**
 * Class BatchTranslationLegacyController for backend modules used in TYPO3 V10 + V11
 */
class BatchTranslationLegacyController extends BatchTranslationBaseController
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
     * 
     * @return void
     */
    public function batchTranslationLegacyAction()
    {
        $this->loadViewData();
    }

}
