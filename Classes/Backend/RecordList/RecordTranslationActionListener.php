<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Backend\RecordList;

use ThieleUndKlose\Autotranslate\Service\RecordTranslationConfigurationService;
use TYPO3\CMS\Backend\RecordList\Event\ModifyRecordListRecordActionsEvent;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Page\PageRenderer;

class RecordTranslationActionListener
{
    private IconFactory $iconFactory;

    private RecordTranslationConfigurationService $recordTranslationConfigurationService;

    private PageRenderer $pageRenderer;

    public function __construct(
        IconFactory $iconFactory,
        RecordTranslationConfigurationService $recordTranslationConfigurationService,
        PageRenderer $pageRenderer
    ) {
        $this->iconFactory = $iconFactory;
        $this->recordTranslationConfigurationService = $recordTranslationConfigurationService;
        $this->pageRenderer = $pageRenderer;
    }

    public function __invoke(ModifyRecordListRecordActionsEvent $event): void
    {
        try {
            $configuration = $this->recordTranslationConfigurationService->getConfiguration(
                $event->getTable(),
                $event->getRecord()
            );
        } catch (\Throwable $exception) {
            $configuration = null;
        }
        if ($configuration === null) {
            return;
        }

        $this->pageRenderer->loadJavaScriptModule('@thieleundklose/autotranslate/record-translation-trigger.js');

        $label = $this->getLanguageService()->sL(
            'LLL:EXT:autotranslate/Resources/Private/Language/locallang_mod.xlf:record_translation.button'
        ) ?: 'Translate record';

        $button = sprintf(
            '<button type="button" class="btn btn-default t3js-autotranslate-record-trigger" data-table="%s" data-uid="%d" title="%s" aria-label="%s">%s</button>',
            htmlspecialchars($event->getTable(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            (int)$event->getRecord()['uid'],
            htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            $this->iconFactory->getIcon('autotranslate-extension', Icon::SIZE_SMALL)->render()
        );

        $event->setAction($button, 'autotranslateRecord', 'secondary', 'localize');
    }

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
