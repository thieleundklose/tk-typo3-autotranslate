<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Controller;

use Psr\Http\Message\ResponseInterface;
use ThieleUndKlose\Autotranslate\Domain\Model\BatchItem;
use TYPO3\CMS\Backend\Template\Components\MultiRecordSelection\Action;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Backend module controller for batch translation management
 */
class BatchTranslationController extends BatchTranslationBaseController
{
    private const TABLE_BATCH_ITEM = 'tx_autotranslate_batch_item';
    private const TABLE_LOG = 'tx_autotranslate_log';

    protected ?ModuleTemplateFactory $moduleTemplateFactory = null;

    public function injectModuleTemplateFactory(ModuleTemplateFactory $factory): void
    {
        $this->moduleTemplateFactory = $factory;
    }

    public function defaultAction(): ResponseInterface
    {
        $view = $this->createModuleTemplate();
        $view->assignMultiple($this->getCommonTemplateVariables($this->getBatchTranslationData()));
        $view->assign('actions', $this->getBatchItemActions());

        $this->addDeeplApiKeyInfoMessage();

        return $view->renderResponse('Default');
    }

    // =========================================================================
    // Actions
    // =========================================================================

    private function createModuleTemplate(): ModuleTemplate
    {
        $view = $this->moduleTemplateFactory->create($this->request);

        $this->addMainMenu($view);
        $this->addLevelMenu($view);
        $this->setPageMetaInfo($view);

        $view->setFlashMessageQueue($this->getFlashMessageQueue());

        if ($this->pageUid === 0) {
            $this->addFlashMessage(
                'No page selected',
                'Please select a page first.',
                ContextualFeedbackSeverity::WARNING
            );
        }

        return $view;
    }

    private function addMainMenu(ModuleTemplate $view): void
    {
        $menuRegistry = $view->getDocHeaderComponent()->getMenuRegistry();
        // @extensionScannerIgnoreLine
        $menu = $menuRegistry->makeMenu()->setIdentifier('BatchTranslationMenu');

        $menuItems = [
            'default' => 'LLL:EXT:autotranslate/Resources/Private/Language/locallang_mod.xlf:mlang_labels_tablabel',
            'showLogs' => 'LLL:EXT:autotranslate/Resources/Private/Language/locallang_mod.xlf:mlang_labels_menu_show_logs',
        ];

        $currentAction = $this->request->getControllerActionName();
        $lang = $this->getLanguageService();

        foreach ($menuItems as $action => $labelKey) {
            // @extensionScannerIgnoreLine
            $menuItem = $menu->makeMenuItem()
                ->setTitle($lang->sL($labelKey))
                ->setHref($this->uriBuilder->reset()->uriFor($action, [], 'BatchTranslation'))
                ->setActive($currentAction === $action);
            $menu->addMenuItem($menuItem);
        }

        $menuRegistry->addMenu($menu);
    }

    private function addLevelMenu(ModuleTemplate $view): void
    {
        if ($this->request->getControllerActionName() !== 'default') {
            return;
        }

        $menuRegistry = $view->getDocHeaderComponent()->getMenuRegistry();
        // @extensionScannerIgnoreLine
        $menu = $menuRegistry->makeMenu()->setIdentifier('BatchTranslationLevels');
        $lang = $this->getLanguageService();

        foreach (self::MENU_LEVEL_ITEMS as $level) {
            // @extensionScannerIgnoreLine
            $menuItem = $menu->makeMenuItem()
                ->setTitle($lang->sL("LLL:EXT:autotranslate/Resources/Private/Language/locallang_mod.xlf:mlang_labels_menu_level.{$level}"))
                ->setHref($this->uriBuilder->reset()->uriFor('default', ['levels' => $level], 'BatchTranslation'))
                ->setActive($this->levels === $level);
            $menu->addMenuItem($menuItem);
        }

        $menuRegistry->addMenu($menu);
    }

    // =========================================================================
    // Module Template Setup
    // =========================================================================

    private function setPageMetaInfo(ModuleTemplate $view): void
    {
        $permissionClause = $this->getBackendUser()->getPagePermsClause(Permission::PAGE_SHOW);
        $pageRecord = BackendUtility::readPageAccess($this->pageUid, $permissionClause);

        if ($pageRecord) {
            // @extensionScannerIgnoreLine
            $view->getDocHeaderComponent()->setMetaInformation($pageRecord);
        }
    }

    private function getBatchItemActions(): array
    {
        // @extensionScannerIgnoreLine
        $returnUrl = $this->request->getAttribute('normalizedParams')->getRequestUri();
        $lang = $this->getLanguageService();

        return [
            $this->createMultiRecordAction('execute', self::TABLE_BATCH_ITEM, $returnUrl, [
                'icon' => 'actions-play',
                'label' => 'LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:autotranslate_batch.function.translate',
            ]),
            $this->createMultiRecordAction('edit', self::TABLE_BATCH_ITEM, $returnUrl, [
                'icon' => 'actions-open',
                'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:cm.edit',
            ]),
            $this->createDeleteAction(self::TABLE_BATCH_ITEM, $returnUrl, $lang),
        ];
    }

    private function createMultiRecordAction(string $name, string $table, string $returnUrl, array $options): Action
    {
        return new Action(
            $name,
            [
                'idField' => 'uid',
                'tableName' => $table,
                'returnUrl' => $returnUrl,
            ],
            $options['icon'],
            $options['label']
        );
    }

    private function createDeleteAction(string $table, string $returnUrl, LanguageService $lang): Action
    {
        return new Action(
            'delete',
            [
                'idField' => 'uid',
                'tableName' => $table,
                'title' => $lang->sL('LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:autotranslate_batch.function.delete.title'),
                'content' => $lang->sL('LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:autotranslate_batch.function.delete.content'),
                'ok' => $lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:cm.delete'),
                'cancel' => $lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.cancel'),
                'returnUrl' => $returnUrl,
            ],
            'actions-edit-delete',
            'LLL:EXT:autotranslate/Resources/Private/Language/locallang_db.xlf:autotranslate_batch.function.delete'
        );
    }

    // =========================================================================
    // Multi-Record Selection Actions
    // =========================================================================

    public function showLogsAction(): ResponseInterface
    {
        $view = $this->createModuleTemplate();
        $view->assignMultiple($this->getLogData());
        $view->assign('actions', $this->getLogActions());

        return $view->renderResponse('ShowLogs');
    }

    private function getLogActions(): array
    {
        // @extensionScannerIgnoreLine
        $returnUrl = $this->request->getAttribute('normalizedParams')->getRequestUri();
        $lang = $this->getLanguageService();

        return [
            $this->createDeleteAction(self::TABLE_LOG, $returnUrl, $lang),
        ];
    }

    public function createAction(): ResponseInterface
    {
        $data = $this->resolveFormData('batchItem');

        $batchItem = new BatchItem();
        $batchItem->setPid((int)($data['pid'] ?? $this->pageUid));
        $batchItem->setPriority((string)($data['priority'] ?? BatchItem::PRIORITY_MEDIUM));
        $batchItem->setSysLanguageUid((int)($data['sysLanguageUid'] ?? 0));
        $batchItem->setMode((string)($data['mode'] ?? ''));
        $batchItem->setFrequency((string)($data['frequency'] ?? ''));

        $translateDate = !empty($data['translate'])
            ? new \DateTime($data['translate'])
            : new \DateTime();
        $batchItem->setTranslate($translateDate);

        $levels = (int)($this->resolveFormValue('recursive', 0));
        $this->createActionAbstract($batchItem, $levels);

        $redirectAction = (string)($this->resolveFormValue('redirectAction', 'default'));
        return $this->redirect($redirectAction);
    }

    /**
     * Resolve form data array from Extbase arguments, namespaced POST data, or raw query params.
     * The <f:form> ViewHelper wraps fields under the Extbase namespace, but
     * $this->queryParams reads from the raw PSR-7 request (top-level keys only).
     */
    private function resolveFormData(string $argumentName): array
    {
        // 1. Extbase request arguments (resolved from namespace)
        if ($this->request->hasArgument($argumentName)) {
            $value = $this->request->getArgument($argumentName);
            if (is_array($value)) {
                return $value;
            }
        }

        // 2. Raw query params (top-level, for non-<f:form> submissions)
        if (isset($this->queryParams[$argumentName]) && is_array($this->queryParams[$argumentName])) {
            return $this->queryParams[$argumentName];
        }

        return [];
    }

    /**
     * Resolve a single form value from Extbase arguments or raw query params.
     */
    private function resolveFormValue(string $argumentName, mixed $default = null): mixed
    {
        if ($this->request->hasArgument($argumentName)) {
            return $this->request->getArgument($argumentName);
        }

        return $this->queryParams[$argumentName] ?? $default;
    }

    protected function initializeAction(): void
    {
        parent::initializeAction();

        $this->moduleTemplateFactory ??= GeneralUtility::makeInstance(ModuleTemplateFactory::class);
    }
}
