<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Task;

use ThieleUndKlose\Autotranslate\Service\BatchTranslationRunner;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\ProgressProviderInterface;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

/**
 * Scheduler task for batch translation.
 *
 * Delegates all work to BatchTranslationRunner and adds
 * a visual progress bar + status text via TYPO3's ProgressProviderInterface.
 */
class BatchTranslationTask extends AbstractTask implements ProgressProviderInterface
{
    /**
     * Number of items to process per run.
     */
    public int $itemsPerRun = 50;

    public function execute(): bool
    {
        $this->initializeBackendContext();

        $runner = $this->getRunner();
        $runner->processBatch($this->itemsPerRun);

        return true;
    }

    /**
     * Returns the progress of the batch translation as a percentage (0.0 - 100.0).
     * TYPO3 Scheduler renders this as a visual progress bar in the task list.
     */
    public function getProgress(): float
    {
        $runner = $this->getRunner();
        $totalItems = $runner->countTotalItems();

        if ($totalItems === 0) {
            return 100.0;
        }

        $pendingItems = $runner->countPendingItems();
        $errorItems = $runner->countErrorItems();
        $doneItems = max(0, $totalItems - $pendingItems - $errorItems);

        return round(($doneItems / $totalItems) * 100, 2);
    }

    /**
     * Shown in the Scheduler module task list as additional info text.
     */
    public function getAdditionalInformation(): string
    {
        $runner = $this->getRunner();
        $lastRun = $runner->getLastRunStatistics();

        $totalItems = $runner->countTotalItems();
        $pendingItems = $runner->countPendingItems();
        $errorItems = $runner->countErrorItems();
        $doneItems = max(0, $totalItems - $pendingItems - $errorItems);

        if ($totalItems === 0) {
            $info = 'Items per run: ' . $this->itemsPerRun;
            if ($lastRun === null) {
                return $info . ' | No items in queue';
            }
            return $info . ' | Queue empty | Last run: ' . date('d.m.Y H:i', $lastRun['timestamp'] ?? 0);
        }

        $statusLine = sprintf(
            '%d done, %d pending, %d errors (%d total)',
            $doneItems,
            $pendingItems,
            $errorItems,
            $totalItems
        );

        $lastRunLine = '';
        if ($lastRun !== null) {
            $timestamp = $lastRun['timestamp'] ?? 0;
            $agoMinutes = (int)floor((time() - $timestamp) / 60);
            $lastRunLine = sprintf(
                ' | Last: %s (%d min ago), %d OK, %d failed',
                date('d.m.Y H:i', $timestamp),
                $agoMinutes,
                $lastRun['succeeded'] ?? 0,
                $lastRun['failed'] ?? 0
            );
        }

        return sprintf('Items/run: %d | %s%s', $this->itemsPerRun, $statusLine, $lastRunLine);
    }

    private function initializeBackendContext(): void
    {
        if (PHP_SAPI !== 'cli') {
            return;
        }

        Bootstrap::initializeBackendAuthentication();
        $GLOBALS['TYPO3_REQUEST'] ??= (new ServerRequest())
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
    }

    private function getRunner(): BatchTranslationRunner
    {
        return GeneralUtility::makeInstance(BatchTranslationRunner::class);
    }
}
