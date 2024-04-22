<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Controller;

use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\Components\Menu\Menu;
use TYPO3\CMS\Backend\Template\Components\Menu\MenuItem;
use TYPO3\CMS\Backend\View\BackendTemplateView;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;

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

    }
    /**
     * 
     * @return void
     */
    public function batchTranslationLegacyAction()
    {
        $this->view->assignMultiple($this->getBatchTranslationData());

        if ($this->pageUid === 0) {
            $this->addFlashMessage(
                'Please select a page first.',
                'No page selected',
                AbstractMessage::WARNING
            );
        }

        $this->view->assign('pageUid', $this->pageUid);
        $this->view->assign('routeBackendModule', 'web_AutotranslateM1');

    }

        /**
     * Initializes the view before invoking an action method.
     *
     * @param ViewInterface $view The view to be initialized
     * @return void
     * @api
     */
    protected function initializeView(ViewInterface $view)
    {
        if ($view instanceof BackendTemplateView) {
            parent::initializeView($view);
        }
        $pageRenderer = $view->getModuleTemplate()->getPageRenderer();
        $pageRenderer->loadRequireJsModule('TYPO3/CMS/Autotranslate/BackendLegacyModule');
        // Make localized labels available in JavaScript context
        // $pageRenderer->addInlineLanguageLabelFile('EXT:examples/Resources/Private/Language/locallang.xlf');

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
    }
    
    /**
     * Function will be called before every other action
     */
    protected function initializeAction()
    {
        $this->defaultViewObjectName = BackendTemplateView::class;
        $this->pageUid = (int)$GLOBALS['_GET']['id'] ?? 0;
        parent::initializeAction();
    }

}
