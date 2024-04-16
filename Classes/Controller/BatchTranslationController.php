<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Controller;

use TYPO3\CMS\Backend\Attribute\AsController;
use ThieleUndKlose\Autotranslate\Domain\Repository\BatchItemRepository;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Http\HtmlResponse;

/**
 * Class BatchTranslationController for backend modules used in TYPO3 V12
 */
#[AsController]
final class BatchTranslationController extends BatchTranslationBaseController
{

    /**
     * Class constructor.
     *
     * This function is called when a new object of the class is created.
     * It initializes the object's properties and performs any necessary setup.
     *
     * @param ModuleTemplateFactory $moduleTemplateFactory Description of the first parameter.
     * @param BatchItemRepository $batchItemRepository Description of the second parameter.
     * @return void
     */
    public function __construct(
        protected readonly ModuleTemplateFactory $moduleTemplateFactory,
        protected BatchItemRepository $batchItemRepository
    ) {
    }
    
    /**
     * @return HtmlResponse
     */
    public function batchTranslationAction()
    {
        $this->loadViewData();

        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        // Adding title, menus, buttons, etc. using $moduleTemplate ...
        $moduleTemplate->setContent($this->view->render());
        return $this->htmlResponse($moduleTemplate->renderContent());

    }

}
