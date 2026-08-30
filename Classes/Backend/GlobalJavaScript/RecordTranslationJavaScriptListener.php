<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Backend\GlobalJavaScript;

use TYPO3\CMS\Backend\Controller\Event\AfterBackendPageRenderEvent;
use TYPO3\CMS\Core\Page\PageRenderer;

class RecordTranslationJavaScriptListener
{
    private PageRenderer $pageRenderer;

    public function __construct(PageRenderer $pageRenderer)
    {
        $this->pageRenderer = $pageRenderer;
    }

    public function __invoke(AfterBackendPageRenderEvent $event): void
    {
        $this->pageRenderer->loadJavaScriptModule('@thieleundklose/autotranslate/record-translation-trigger.js');
    }
}
