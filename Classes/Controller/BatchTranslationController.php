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
        $view = $this->initializeModuleTemplate($this->request);
        $view->assignMultiple($this->getBatchTranslationData($this->levels));
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

    public function setLevelsAction(int $levels): ResponseInterface
    {
        $this->levels = $levels;
        $view = $this->initializeModuleTemplate($this->request);
        $view->assignMultiple($this->getBatchTranslationData($this->levels));

        return $view->renderResponse();
    }

    /**
     * Generates the action menu
     */
    protected function initializeModuleTemplate(
        ServerRequestInterface $request
    ): ModuleTemplate {
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
        $menuLevelItems = [
            'Level0' => [
                'controller' => 'BatchTranslation',
                'action' => 'setLevels',
                'label' => $this->getLanguageService()->sL('LLL:EXT:autotranslate/Resources/Private/Language/locallang_mod.xlf:mlang_labels_menu_level0'),
            ],
            'Level1' => [
                'controller' => 'BatchTranslation',
                'action' => 'setLevels',
                'label' => $this->getLanguageService()->sL('LLL:EXT:autotranslate/Resources/Private/Language/locallang_mod.xlf:mlang_labels_menu_level1'),
            ],
            'Level2' => [
                'controller' => 'BatchTranslation',
                'action' => 'setLevels',
                'label' => $this->getLanguageService()->sL('LLL:EXT:autotranslate/Resources/Private/Language/locallang_mod.xlf:mlang_labels_menu_level2'),
            ],
            'Level3' => [
                'controller' => 'BatchTranslation',
                'action' => 'setLevels',
                'label' => $this->getLanguageService()->sL('LLL:EXT:autotranslate/Resources/Private/Language/locallang_mod.xlf:mlang_labels_menu_level3'),
            ],
            'Level4' => [
                'controller' => 'BatchTranslation',
                'action' => 'setLevels',
                'label' => $this->getLanguageService()->sL('LLL:EXT:autotranslate/Resources/Private/Language/locallang_mod.xlf:mlang_labels_menu_level4'),
            ],
            'LevelINF' => [
                'controller' => 'BatchTranslation',
                'action' => 'setLevels',
                'label' => $this->getLanguageService()->sL('LLL:EXT:autotranslate/Resources/Private/Language/locallang_mod.xlf:mlang_labels_menu_levelINF'),
            ],
        ];
        $view = $this->moduleTemplateFactory->create($request);

        $menu = $view->getDocHeaderComponent()->getMenuRegistry()->makeMenu();
        $menu2 = $view->getDocHeaderComponent()->getMenuRegistry()->makeMenu();
        $menu2->setIdentifier('BatchTranslationLevels');
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

        // Recursive Level Items
        $levelContext = '';
        foreach ($menuLevelItems as $menuItemConfig) {
            $isActive = $this->request->getControllerActionName() === $menuItemConfig['action'];
            $menuItem = $menu2->makeMenuItem()
                ->setTitle($menuItemConfig['label'])
                ->setHref($this->uriBuilder->reset()->uriFor(
                    $menuItemConfig['action'],
                    ['levels' => $menuItemConfig['label'] === 'LevelINF' ? 250 : (int)str_replace('Level', '', $menuItemConfig['label'])],
                    $menuItemConfig['controller']
                ))
                ->setActive($isActive);
            $menu2->addMenuItem($menuItem);
            if ($isActive) {
                $levelContext = $menuItemConfig['label'];
            }
        }

        $view->getDocHeaderComponent()->getMenuRegistry()->addMenu($menu);

        if ($context === 'Batch Translation list'){
            $view->getDocHeaderComponent()->getMenuRegistry()->addMenu($menu2);
        }
        $view->assign('selected Context', $context);

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
