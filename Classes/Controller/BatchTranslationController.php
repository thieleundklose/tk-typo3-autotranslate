<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Controller;

use Psr\Http\Message\ServerRequestInterface;
use ThieleUndKlose\Autotranslate\Domain\Repository\BatchItemRepository;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class BatchTranslationController for backend modules used in TYPO3 V12
 */
class BatchTranslationController extends BatchTranslationBaseController
{

    private int $pageUid;

    public function __construct(
        protected readonly ModuleTemplateFactory $moduleTemplateFactory,
        protected readonly BatchItemRepository $batchItemRepository
    ) {}
    
    /**
     * @return HtmlResponse
     */
    public function batchTranslationAction(): ResponseInterface
    {

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
        $view = $this->moduleTemplateFactory->create($request);

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

        if ($this->pageUid > 0) {
            // create new item at selected page
            $backendUriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
            $uriParameters = [
                'edit' => [
                    'tx_autotranslate_batch_item' => [
                        $this->pageUid => 'new',
                    ],
                ],
                'returnUrl' => $backendUriBuilder->buildUriFromRoute(
                    'web_autotranslate', 
                    [
                        'id' => $this->pageUid
                    ]
                ),
            ];
            $createNewItemLink = $backendUriBuilder->buildUriFromRoute(
                'record_edit',
                $uriParameters
            );
            $view->assign('createNewItemLink', $createNewItemLink);
        }
        
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

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }

    protected function getBackendUserAuthentication(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
