<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Controller;

use ThieleUndKlose\Autotranslate\Domain\Repository\BatchItemRepository;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use Psr\Http\Message\ResponseInterface;


/**
 * Class ModuleController for backend modules
 */
class BatchTranslationModuleController extends ActionController
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
     * @return ResponseInterface
     */
    public function listAction(): ResponseInterface
    {
        // TODO: add dynamic levels
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
        return $this->htmlResponse();
    }


    
}
