<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Controller;

use ThieleUndKlose\Autotranslate\Domain\Model\BatchItem;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\Components\Menu\Menu;
use TYPO3\CMS\Backend\Template\Components\Menu\MenuItem;
use TYPO3\CMS\Backend\View\BackendTemplateView;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class BatchTranslationLegacyController for backend modules used in TYPO3 V10 + V11
 */
class BatchTranslationLegacyController extends BatchTranslationBaseController
{

    /**
     *
     * @return void
     */
    public function showLogsLegacyAction()
    {
        $this->initializeModuleTemplate();
        $this->addMessage(
            'Not yet implemented.',
            'Planned for future versions.',
            self::MESSAGE_WARNING
        );
    }

    /**
     *
     * @return void
     */
    public function batchTranslationLegacyAction()
    {
        $this->initializeModuleTemplate();
        $this->view->assignMultiple($this->getBatchTranslationData());
    }

    /**
     * Function will be called before every other action
     */
    protected function initializeAction(): void
    {
        $this->defaultViewObjectName = BackendTemplateView::class;
        parent::initializeAction();
    }

    /**
     * Create items for given form parameters and redirect to previous action
     * @param BatchItem $batchItem
     * @return void
     */
    public function createAction(BatchItem $batchItem)
    {
        $this->createActionAbstract($batchItem, (int)$this->queryParams['recursive']);

        $this->redirect($this->queryParams['redirectAction']);
    }

    /**
     * @return void
     */
    protected function initializeModuleTemplate()
    {
        /** @var PageRenderer */
        $pageRenderer = $this->view->getModuleTemplate()->getPageRenderer();
        $pageRenderer->loadRequireJsModule('TYPO3/CMS/Autotranslate/BackendLegacyModule');
        $pageRenderer->addCssFile('EXT:autotranslate/Resources/Public/Css/Backend.css');
        // TODO check and fix date selector for all typo3 versions
        $pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/DateTimePicker');


        // Make localized labels available in JavaScript context
        // $pageRenderer->addInlineLanguageLabelFile('EXT:examples/Resources/Private/Language/locallang.xlf');

        // Main Menu Items
        // Add action menu
        /** @var Menu $menu */
        $menu = GeneralUtility::makeInstance(Menu::class);
        $menu->setIdentifier('BatchTranslationMenu');

        /** @var UriBuilder $uriBuilder */
        $uriBuilder = $this->objectManager->get(UriBuilder::class);
        $uriBuilder->setRequest($this->request);

        // Add menu items
        /** @var MenuItem $menuItem */
        $menuItem = GeneralUtility::makeInstance(MenuItem::class);
        $menuItems = [
            'batchTranslationLegacy' => [
                'controller' => 'BatchTranslationLegacy',
                'action' => 'batchTranslationLegacy',
                'label' => $this->getLanguageService()->sL('LLL:EXT:autotranslate/Resources/Private/Language/locallang_mod.xlf:mlang_labels_tablabel'),
            ],
            'showLogsLegacy' => [
                'controller' => 'BatchTranslationLegacy',
                'action' => 'showLogsLegacy',
                'label' => $this->getLanguageService()->sL('LLL:EXT:autotranslate/Resources/Private/Language/locallang_mod.xlf:mlang_labels_menu_show_logs'),
            ],
        ];
        foreach ($menuItems as $menuItemConfig) {
            $isActive = $this->actionMethodName === $menuItemConfig['action'] . 'Action';
            $menuItem->setTitle(
                $menuItemConfig['label']
            );

            $uri = $uriBuilder->reset()->uriFor(
                $menuItemConfig['action'],
                [],
                $menuItemConfig['controller']
            );
            $menuItem->setActive($isActive)->setHref($uri);
            $menu->addMenuItem($menuItem);
        }
        $this->view->getModuleTemplate()->getDocHeaderComponent()->getMenuRegistry()->addMenu($menu);

        // Recursive Level Items
        /** @var Menu $menu */
        $menu = GeneralUtility::makeInstance(Menu::class);
        $menu->setIdentifier("BatchTranslationMenuLevels");

        /** @var UriBuilder $uriBuilder */
        $uriBuilder = $this->objectManager->get(UriBuilder::class);
        $uriBuilder->setRequest($this->request);

        // Add menu items
        /** @var MenuItem $menuItem */
        $menuItem = GeneralUtility::makeInstance(MenuItem::class);
        foreach ($this->menuLevelItems as $level) {
            $menuItem->setTitle(
                $this->getLanguageService()->sL('LLL:EXT:autotranslate/Resources/Private/Language/locallang_mod.xlf:mlang_labels_menu_level.' . $level)
            );
            $uri = $uriBuilder->reset()->uriFor(
                'batchTranslationLegacy',
                ['levels' => $level],
                'BatchTranslationLegacy'
            );
            $menuItem->setActive($this->levels === $level)->setHref($uri);
            $menu->addMenuItem($menuItem);
        }
        $this->view->getModuleTemplate()->getDocHeaderComponent()->getMenuRegistry()->addMenu($menu);

        if ($this->pageUid === 0) {
            $this->addMessage(
                'No page selected',
                'Please select a page first.',
                self::MESSAGE_WARNING
            );

        }

    }

}
