<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Controller;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use Psr\Http\Message\ResponseInterface;
use ThieleUndKlose\Autotranslate\Domain\Model\BatchItem;
use ThieleUndKlose\Autotranslate\Service\BatchTranslationService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Backend\Template\Components\Menu\MenuItem;

/**
 * Class BatchTranslationController for backend modules used in TYPO3 V12
 */
class BatchTranslationController extends BatchTranslationBaseController
{
    /**
     * @var \TYPO3\CMS\Backend\Template\ModuleTemplateFactory|null
     */
    protected $moduleTemplateFactory = null;

    /**
     * @var BatchTranslationService
     */
    protected $batchTranslationService;

    /**
     * @param BatchTranslationService $batchTranslationService
     */
    function __construct(BatchTranslationService $batchTranslationService)
    {
        $this->batchTranslationService = $batchTranslationService;
        // Initialize without dependency injection to throw no error in TYPO3 v10
        $this->moduleTemplateFactory = GeneralUtility::makeInstance('TYPO3\\CMS\\Backend\\Template\\ModuleTemplateFactory');
    }

    /**
     * @return HtmlResponse
     */
    public function batchTranslationAction(): ResponseInterface
    {
        if ($this->request->hasArgument('execute')) {
            $executeUids = GeneralUtility::trimExplode(',' ,$this->request->getArgument('execute'));
            foreach ($executeUids as $uid) {
                $item = $this->batchItemRepository->findByUid((int)$uid);
                if ($item instanceof BatchItem) {
                    $res = $this->batchTranslationService->translate($item);
                    if ($res === true) {
                        $item->markAsTranslated();
                        $this->addMessage(
                            'Successfully Translated',
                            sprintf('Item with uid %s was translated.', $item->getUid()),
                            self::MESSAGE_OK
                        );
                    } else {
                        $this->addMessage(
                            'Error while translating',
                            sprintf('Item with uid %s could not be translated.', $item->getUid()),
                            self::MESSAGE_ERROR
                        );
                    }
                    $this->batchItemRepository->update($item);
                }
            }
        }

        if ($this->request->hasArgument('reset')) {
            $executeUids = GeneralUtility::trimExplode(',' ,$this->request->getArgument('reset'));
            foreach ($executeUids as $uid) {
                $item = $this->batchItemRepository->findByUid((int)$uid);
                if ($item instanceof BatchItem) {
                    $item->setTranslated();
                    $item->setError('');
                    $this->addMessage(
                        'Reset successful',
                        sprintf('Translated date for item with uid %s was removed.', $item->getUid()),
                        self::MESSAGE_OK
                    );
                    $this->batchItemRepository->update($item);
                }
            }
        }

        $view = $this->initializeModuleTemplate($this->request);
        $view->assignMultiple($this->getBatchTranslationData());

        return $view->renderResponse();
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

        // Recursive Level Items
        $menu = $view->getDocHeaderComponent()->getMenuRegistry()->makeMenu();
        $menu->setIdentifier('BatchTranslationLevels');
        foreach ($this->menuLevelItems as $level) {
            /** @var MenuItem $menuItem */
            $menuItem = $menu->makeMenuItem()
                ->setTitle($this->getLanguageService()->sL('LLL:EXT:autotranslate/Resources/Private/Language/locallang_mod.xlf:mlang_labels_menu_level.' . $level))
                ->setHref($this->uriBuilder->reset()->uriFor(
                    'batchTranslation',
                    ['levels' => $level],
                    'BatchTranslation'
                ))
                ->setActive($this->levels === $level);

            $menu->addMenuItem($menuItem);
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
            $this->addMessage(
                'No page selected',
                'Please select a page first.',
                self::MESSAGE_WARNING
            );
        }

        return $view;
    }

}
