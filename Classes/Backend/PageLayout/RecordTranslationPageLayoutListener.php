<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Backend\PageLayout;

use TYPO3\CMS\Backend\Controller\Event\ModifyPageLayoutContentEvent;
use TYPO3\CMS\Core\Page\PageRenderer;

class RecordTranslationPageLayoutListener
{
    public function __construct(
        private readonly PageRenderer $pageRenderer,
    ) {}

    public function __invoke(ModifyPageLayoutContentEvent $event): void
    {
        $this->pageRenderer->loadJavaScriptModule('@thieleundklose/autotranslate/record-translation-trigger.js');
    }
}
