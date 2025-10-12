<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Controller;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\Components\Menu\MenuItem;
use TYPO3\CMS\Backend\Template\Components\MultiRecordSelection\Action;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use Psr\Http\Message\ResponseInterface;
use ThieleUndKlose\Autotranslate\Domain\Model\BatchItem;
use ThieleUndKlose\Autotranslate\Utility\FlashMessageUtility;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class BatchTranslationController for backend modules used in TYPO3 V12
 */
class BatchTranslationController extends BatchTranslationBaseController
{
    /**
     * @var ModuleTemplateFactory|null
     */
    protected $moduleTemplateFactory = null;

    function __construct()
    {
        // Initialize without dependency injection to throw no error on php v7.4
        $this->moduleTemplateFactory = GeneralUtility::makeInstance(ModuleTemplateFactory::class);
    }

    /**
     * @return HtmlResponse
     */
    public function defaultAction(): ResponseInterface
    {

        $view = $this->initializeModuleTemplate($this->request);

        $data = $this->getBatchTranslationData();
        $view->assignMultiple($this->getCommonTemplateVariables($data));

        $requestUri = $this->request->getAttribute('normalizedParams')->getRequestUri();
        $languageService = $this->getLanguageService();

        $view->assign('actions', [
            new Action(
                'execute',
                [
                    'idField' => 'uid',
                    'tableName' => 'tx_autotranslate_batch_item',
                    'returnUrl' => $requestUri,
                ],
                'actions-play',
                'LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:autotranslate_batch.function.translate'
            ),
            new Action(
                'edit',
                [
                    'idField' => 'uid',
                    'tableName' => 'tx_autotranslate_batch_item',
                    'returnUrl' => $requestUri,
                ],
                'actions-open',
                'LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:cm.edit'
            ),

            new Action(
                'delete',
                [
                    'idField' => 'uid',
                    'tableName' => 'tx_autotranslate_batch_item',
                    'title' => $languageService->sL('LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:autotranslate_batch.function.delete.title'),
                    'content' => $languageService->sL('LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:autotranslate_batch.function.delete.content'),
                    'ok' => $languageService->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:cm.delete'),
                    'cancel' => $languageService->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.cancel'),
                    'returnUrl' => $requestUri,
                ],
                'actions-edit-delete',
                'LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:autotranslate_batch.function.delete'
            )
            // ToDo: Implement multiselect reset action
        ]);

        $this->addDeeplApiKeyInfoMessage();
        return $view->renderResponse('Default');
    }

    /**
     * Create items for given form parameters and redirect to previous action
     * @param BatchItem $batchItem
     * @return ResponseInterface
     */
    public function createAction(BatchItem $batchItem): ResponseInterface
    {
        $this->createActionAbstract($batchItem, (int)$this->queryParams['recursive']);

        return $this->redirect($this->queryParams['redirectAction']);
    }

    /**
     * @return HtmlResponse
     */
    public function showLogsAction(): ResponseInterface
    {
        $view = $this->initializeModuleTemplate($this->request);
        $view->assignMultiple($this->getLogData());

        $requestUri = $this->request->getAttribute('normalizedParams')->getRequestUri();
        $languageService = $this->getLanguageService();

        $view->assign('actions', [
            new Action(
                'delete',
                [
                    'idField' => 'uid',
                    'tableName' => 'tx_autotranslate_log',
                    'title' => $languageService->sL('LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:autotranslate_batch.function.delete.title'),
                    'content' => $languageService->sL('LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:autotranslate_batch.function.delete.content'),
                    'ok' => $languageService->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:cm.delete'),
                    'cancel' => $languageService->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.cancel'),
                    'returnUrl' => $requestUri,
                ],
                'actions-edit-delete',
                'LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:autotranslate_batch.function.delete'
            )
            // ToDo: Implement multiselect reset action
        ]);

        return $view->renderResponse('ShowLogs');
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
                'action' => 'default',
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
        }
        $view->getDocHeaderComponent()->getMenuRegistry()->addMenu($menu);

        if ($this->request->getControllerActionName() === 'default') {
            // Recursive Level Items
            $menu = $view->getDocHeaderComponent()->getMenuRegistry()->makeMenu();
            $menu->setIdentifier('BatchTranslationLevels');
            foreach ($this->menuLevelItems as $level) {
                /** @var MenuItem $menuItem */
                $menuItem = $menu->makeMenuItem()
                    ->setTitle($this->getLanguageService()->sL('LLL:EXT:autotranslate/Resources/Private/Language/locallang_mod.xlf:mlang_labels_menu_level.' . $level))
                    ->setHref($this->uriBuilder->reset()->uriFor(
                        'default',
                        ['levels' => $level],
                        'BatchTranslation'
                    ))
                    ->setActive($this->levels === $level);
                $menu->addMenuItem($menuItem);
            }
        }

        $view->getDocHeaderComponent()->getMenuRegistry()->addMenu($menu);

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
                'No page selected',
                'Please select a page first.',
                FlashMessageUtility::adjustSeverityForTypo3Version(FlashMessageUtility::MESSAGE_WARNING)
            );
        }

        return $view;
    }
}
