<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Service;

use DateTime;
use ThieleUndKlose\Autotranslate\Domain\Model\BatchItem;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapper;

/**
 * Shared service for batch translation orchestration.
 *
 * Contains all query, counting, persistence and statistics logic
 * used by both the CLI command and the scheduler task.
 */
final class BatchTranslationRunner
{
    private const TABLE_NAME = 'tx_autotranslate_batch_item';
    public const REGISTRY_NAMESPACE = 'tx_autotranslate';
    public const REGISTRY_KEY_LAST_RUN = 'lastBatchRun';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly DataMapper $dataMapper,
        private readonly BatchTranslationService $batchTranslationService,
        private readonly Registry $registry,
    ) {}

    /**
     * Run a batch of translations and return statistics.
     *
     * @return array{processed: int, succeeded: int, failed: int, remaining: int}
     */
    public function processBatch(int $limit): array
    {
        $totalPending = $this->countPendingItems();
        $items = $this->findPendingItems($limit);

        if (empty($items)) {
            $this->storeRunStatistics(0, 0, 0, 0);
            return ['processed' => 0, 'succeeded' => 0, 'failed' => 0, 'remaining' => 0];
        }

        $succeeded = 0;
        $processed = count($items);

        foreach ($items as $item) {
            try {
                if ($this->batchTranslationService->translate($item)) {
                    $item->markAsTranslated();
                    $succeeded++;
                }
            } catch (\Exception) {
                // Error is already stored on the item by BatchTranslationService
            }
            $this->persistBatchItem($item);
        }

        $failed = $processed - $succeeded;
        $remaining = max(0, $totalPending - $processed);

        $this->storeRunStatistics($processed, $succeeded, $failed, $remaining);

        return compact('processed', 'succeeded', 'failed', 'remaining');
    }

    /**
     * Find items waiting to be translated.
     *
     * @return BatchItem[]
     */
    public function findPendingItems(int $limit): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_NAME);
        $queryBuilder->getRestrictions()->removeAll();

        $now = new DateTime();

        $result = $queryBuilder
            ->select('*')
            ->from(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->isNull('translated'),
                    $queryBuilder->expr()->gt('translate', 'translated')
                ),
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->eq('error', $queryBuilder->createNamedParameter('')),
                    $queryBuilder->expr()->isNull('error')
                ),
                $queryBuilder->expr()->lt('translate', $queryBuilder->createNamedParameter($now->getTimestamp())),
                $queryBuilder->expr()->eq('hidden', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->orderBy('priority', 'DESC')
            ->addOrderBy('translate', 'ASC')
            ->setMaxResults($limit)
            ->executeQuery();

        return $this->dataMapper->map(BatchItem::class, $result->fetchAllAssociative());
    }

    /**
     * Count all visible (non-hidden) items.
     */
    public function countTotalItems(): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_NAME);
        $queryBuilder->getRestrictions()->removeAll();

        return (int)$queryBuilder
            ->count('uid')
            ->from(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->eq('hidden', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * Count items that are pending translation.
     */
    public function countPendingItems(): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_NAME);
        $queryBuilder->getRestrictions()->removeAll();

        $now = new DateTime();

        return (int)$queryBuilder
            ->count('uid')
            ->from(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->isNull('translated'),
                    $queryBuilder->expr()->gt('translate', 'translated')
                ),
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->eq('error', $queryBuilder->createNamedParameter('')),
                    $queryBuilder->expr()->isNull('error')
                ),
                $queryBuilder->expr()->lt('translate', $queryBuilder->createNamedParameter($now->getTimestamp())),
                $queryBuilder->expr()->eq('hidden', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * Count items with errors.
     */
    public function countErrorItems(): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_NAME);
        $queryBuilder->getRestrictions()->removeAll();

        return (int)$queryBuilder
            ->count('uid')
            ->from(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->neq('error', $queryBuilder->createNamedParameter('')),
                $queryBuilder->expr()->isNotNull('error'),
                $queryBuilder->expr()->eq('hidden', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * Get the last run statistics from the registry.
     */
    public function getLastRunStatistics(): ?array
    {
        return $this->registry->get(self::REGISTRY_NAMESPACE, self::REGISTRY_KEY_LAST_RUN);
    }

    /**
     * Persist batch item state to database.
     */
    private function persistBatchItem(BatchItem $item): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_NAME);

        $queryBuilder
            ->update(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($item->getUid(), Connection::PARAM_INT))
            )
            ->set('error', $item->getError())
            ->set('translate', $item->getTranslate()->getTimestamp());

        if ($item->getTranslated()) {
            $queryBuilder->set('translated', $item->getTranslated()->getTimestamp());
        }

        $queryBuilder->executeStatement();
    }

    /**
     * Store run statistics in the TYPO3 registry.
     */
    private function storeRunStatistics(int $processed, int $succeeded, int $failed, int $remainingPending): void
    {
        $this->registry->set(self::REGISTRY_NAMESPACE, self::REGISTRY_KEY_LAST_RUN, [
            'timestamp' => time(),
            'processed' => $processed,
            'succeeded' => $succeeded,
            'failed' => $failed,
            'remainingPending' => $remainingPending,
        ]);
    }
}
