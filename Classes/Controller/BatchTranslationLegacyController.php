<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Controller;

use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\Components\Menu\Menu;
use TYPO3\CMS\Backend\Template\Components\Menu\MenuItem;
use TYPO3\CMS\Backend\View\BackendTemplateView;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
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
    }

    /**
     *
     * @return void
     */
    public function batchTranslationLegacyAction()
    {
        // Get and Save selected level in session
        if (version_compare(TYPO3_version, '11.0', '>')) {
            $selectedLevel = (int)($this->request->getQueryParams()['tx_autotranslate_web_autotranslatem1']['levels'] ?? 0);
        } else {
            $selectedLevel = (int)($this->request->getArguments()['tx_autotranslate_web_autotranslatem1']['levels'] ?? 0);
        }
        $req = $this->request;
        $this->view->assign('request', $req);
        $this->getBackendUserAuthentication()->setAndSaveSessionData('autotranslate.levels', $selectedLevel);

        $this->view->assignMultiple($this->getBatchTranslationData());

        $this->initializeView($this->view);
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

        // Main Menu Items
        // Add action menu
        /** @var Menu $menu */
        $menu = GeneralUtility::makeInstance(Menu::class);
        $menu->setIdentifier("BatchTranslationMenu");

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
        // Add action menu
        /** @var Menu $menu */
        $menu = GeneralUtility::makeInstance(Menu::class);
        $menu->setIdentifier("BatchTranslationMenuLevels");

        /** @var UriBuilder $uriBuilder */
        $uriBuilder = $this->objectManager->get(UriBuilder::class);
        $uriBuilder->setRequest($this->request);

        // Add menu items
        /** @var MenuItem $menuItem */
        $menuItem = GeneralUtility::makeInstance(MenuItem::class);
        $menuItems = [
            'Level0' => [
                'controller' => 'BatchTranslationLegacy',
                'action' => 'batchTranslationLegacy',
                'label' => $this->getLanguageService()->sL('LLL:EXT:autotranslate/Resources/Private/Language/locallang_mod.xlf:mlang_labels_menu_level0'),
            ],
            'Level1' => [
                'controller' => 'BatchTranslationLegacy',
                'action' => 'batchTranslationLegacy',
                'label' => $this->getLanguageService()->sL('LLL:EXT:autotranslate/Resources/Private/Language/locallang_mod.xlf:mlang_labels_menu_level1'),
            ],
            'Level2' => [
                'controller' => 'BatchTranslationLegacy',
                'action' => 'batchTranslationLegacy',
                'label' => $this->getLanguageService()->sL('LLL:EXT:autotranslate/Resources/Private/Language/locallang_mod.xlf:mlang_labels_menu_level2'),
            ],
            'Level3' => [
                'controller' => 'BatchTranslationLegacy',
                'action' => 'batchTranslationLegacy',
                'label' => $this->getLanguageService()->sL('LLL:EXT:autotranslate/Resources/Private/Language/locallang_mod.xlf:mlang_labels_menu_level3'),
            ],
            'Level4' => [
                'controller' => 'BatchTranslationLegacy',
                'action' => 'batchTranslationLegacy',
                'label' => $this->getLanguageService()->sL('LLL:EXT:autotranslate/Resources/Private/Language/locallang_mod.xlf:mlang_labels_menu_level4'),
            ],
            'LevelINF' => [
                'controller' => 'BatchTranslationLegacy',
                'action' => 'batchTranslationLegacy',
                'label' => $this->getLanguageService()->sL('LLL:EXT:autotranslate/Resources/Private/Language/locallang_mod.xlf:mlang_labels_menu_levelINF'),
            ],
        ];
        foreach ($menuItems as $menuItemConfig) {
            $sessionLevel = (int)$this->getBackendUserAuthentication()->getSessionData('autotranslate.levels');
            $itemLevel = $menuItemConfig['label'] === "Infinite" ? 250 : (int)str_replace('levels', '', $menuItemConfig['label']);
            $isActive = $sessionLevel === $itemLevel;
            $menuItem->setTitle(
                $menuItemConfig['label']
            );
            $uri = $uriBuilder->reset()->uriFor(
                $menuItemConfig['action'],
                ['levels' => $itemLevel],
                $menuItemConfig['controller']
            );
            $menuItem->setActive($isActive)->setHref($uri);
            $menu->addMenuItem($menuItem);
        }
        $currentMenuPoint = $this->request->getControllerActionName();
        if ($currentMenuPoint === 'batchTranslationLegacy' || $currentMenuPoint === 'setLevels') {
            $this->view->getModuleTemplate()->getDocHeaderComponent()->getMenuRegistry()->addMenu($menu);
        }

        if ($this->pageUid === 0) {
            $this->addFlashMessage(
                'Please select a page first.',
                'No page selected',
                AbstractMessage::WARNING
            );

        }

    }

    /**
     * Function will be called before every other action
     */
    protected function initializeAction()
    {
        $this->defaultViewObjectName = BackendTemplateView::class;
        parent::initializeAction();
    }
}
