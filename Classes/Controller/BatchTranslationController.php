<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Controller;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class BatchTranslationController for backend modules used in TYPO3 V12
 */
class BatchTranslationController extends BatchTranslationBaseController
{
    /**
     * @var \TYPO3\CMS\Backend\Template\ModuleTemplateFactory|null
     */
    protected $moduleTemplateFactory = null;
    protected $levels = 0;

    function __construct()
    {
        // Initialize without dependency injection to throw no error in TYPO3 v10
        $this->moduleTemplateFactory = GeneralUtility::makeInstance('TYPO3\\CMS\\Backend\\Template\\ModuleTemplateFactory');
    }

    /**
     * @return HtmlResponse
     */
    public function batchTranslationAction(): ResponseInterface
    {
        // Save selected level in session
        $selectedLevel = (int)($this->request->getQueryParams()['levels'] ?? 0);
        $this->getBackendUserAuthentication()->setAndSaveSessionData('autotranslate.levels', $selectedLevel);

        $view = $this->initializeModuleTemplate($this->request);
        $view->assignMultiple($this->getBatchTranslationData());

        return $view->renderResponse();
    }

    /**
     * @return HtmlResponse
     */
    public function showLogsAction(): ResponseInterface
    {
        $view = $this->initializeModuleTemplate($this->request);
        return $view->renderResponse();
    }

    /**
     * Generates the action menu
     */
    protected function initializeModuleTemplate(
        ServerRequestInterface $request
    ): ModuleTemplate {
        $view = $this->moduleTemplateFactory->create($request);

        // Main Menu Items
        $menuItems = [
            'batchTranslation' => [
                'controller' => 'BatchTranslation',
                'action' => 'batchTranslation',
                'label' => $this->getLanguageService()->sL('LLL:EXT:autotranslate/Resources/Private/Language/locallang_mod.xlf:mlang_labels_tablabel'),
            ],
            'showLogs' => [
                'controller' => 'BatchTranslation',
                'action' => 'showLogs',
                'label' => $this->getLanguageService()->sL('LLL:EXT:autotranslate/Resources/Private/Language/locallang_mod.xlf:mlang_labels_menu_show_logs'),
            ],
        ];
        $menu = $view->getDocHeaderComponent()->getMenuRegistry()->makeMenu();
        $menu->setIdentifier('BatchTranslationMenu');
        $context = '';
        foreach ($menuItems as $menuItemConfig) {
            $isActive = $this->request->getControllerActionName() === $menuItemConfig['action'];
            $menuItem = $menu->makeMenuItem()
                ->setTitle($menuItemConfig['label'])
                ->setHref($this->uriBuilder->reset()->uriFor(
                    $menuItemConfig['action'],
                    [],
                    $menuItemConfig['controller']
                ))
                ->setActive($isActive);
            $menu->addMenuItem($menuItem);
            if ($isActive) {
                $context = $menuItemConfig['label'];
            }
        }
        $view->getDocHeaderComponent()->getMenuRegistry()->addMenu($menu);

        // Recursive Level Items
        $menuItems = [
            'Level0' => [
                'controller' => 'BatchTranslation',
                'action' => 'batchTranslation',
                'label' => $this->getLanguageService()->sL('LLL:EXT:autotranslate/Resources/Private/Language/locallang_mod.xlf:mlang_labels_menu_level0'),
            ],
            'Level1' => [
                'controller' => 'BatchTranslation',
                'action' => 'batchTranslation',
                'label' => $this->getLanguageService()->sL('LLL:EXT:autotranslate/Resources/Private/Language/locallang_mod.xlf:mlang_labels_menu_level1'),
            ],
            'Level2' => [
                'controller' => 'BatchTranslation',
                'action' => 'batchTranslation',
                'label' => $this->getLanguageService()->sL('LLL:EXT:autotranslate/Resources/Private/Language/locallang_mod.xlf:mlang_labels_menu_level2'),
            ],
            'Level3' => [
                'controller' => 'BatchTranslation',
                'action' => 'batchTranslation',
                'label' => $this->getLanguageService()->sL('LLL:EXT:autotranslate/Resources/Private/Language/locallang_mod.xlf:mlang_labels_menu_level3'),
            ],
            'Level4' => [
                'controller' => 'BatchTranslation',
                'action' => 'batchTranslation',
                'label' => $this->getLanguageService()->sL('LLL:EXT:autotranslate/Resources/Private/Language/locallang_mod.xlf:mlang_labels_menu_level4'),
            ],
            'LevelINF' => [
                'controller' => 'BatchTranslation',
                'action' => 'batchTranslation',
                'label' => $this->getLanguageService()->sL('LLL:EXT:autotranslate/Resources/Private/Language/locallang_mod.xlf:mlang_labels_menu_levelINF'),
            ],
        ];
        $menu = $view->getDocHeaderComponent()->getMenuRegistry()->makeMenu();
        $menu->setIdentifier('BatchTranslationLevels');
        $context= '';
        foreach ($menuItems as $menuItemConfig) {
            $sessionLevel = (int)$this->getBackendUserAuthentication()->getSessionData('autotranslate.levels');
            $itemLevel = $menuItemConfig['label'] === "Infinite" ? 250 : (int)str_replace('levels', '', $menuItemConfig['label']);
            $isActive = $sessionLevel === $itemLevel;

            $menuItem = $menu->makeMenuItem()
                ->setTitle($menuItemConfig['label'])
                ->setHref($this->uriBuilder->reset()->uriFor(
                    $menuItemConfig['action'],
                    ['levels' => $itemLevel],
                    $menuItemConfig['controller']
                ))
                ->setActive($isActive);
                
            $menu->addMenuItem($menuItem);
            if ($isActive) {
                $context = $menuItemConfig['label'];
            }
        }
        $currentMenuPoint = $this->request->getControllerActionName();
        if ($currentMenuPoint === 'batchTranslation' || $currentMenuPoint === 'setLevels'){
            $view->getDocHeaderComponent()->getMenuRegistry()->addMenu($menu);
        }

        $view->setTitle(
            $this->getLanguageService()->sL('LLL:EXT:autotranslate/Resources/Private/Language/locallang_mod.xlf:mlang_tabs_tab'),
            $context
        );

        $permissionClause = $this->getBackendUserAuthentication()->getPagePermsClause(Permission::PAGE_SHOW);
        $pageRecord = BackendUtility::readPageAccess(
            $this->pageUid,
            $permissionClause
        );
        if ($pageRecord) {
            $view->getDocHeaderComponent()->setMetaInformation($pageRecord);
        }

        $view->setFlashMessageQueue($this->getFlashMessageQueue());
        if ($this->pageUid === 0) {
            $this->addFlashMessage(
                'Please select a page first.',
                'No page selected',
                ContextualFeedbackSeverity::WARNING
            );
        }

        $view->assign('pageUid', $this->pageUid);
        $view->assign('routeBackendModule', 'web_autotranslate');

        return $view;
    }

    /**
     * Function will be called before every other action
     */
    protected function initializeAction()
    {
        $this->pageUid = (int)($this->request->getQueryParams()['id'] ?? 0);
        parent::initializeAction();
    }
}
