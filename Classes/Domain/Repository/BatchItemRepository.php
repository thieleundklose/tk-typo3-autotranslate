<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Domain\Repository;

use ThieleUndKlose\Autotranslate\Utility\PageUtility;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

final class BatchItemRepository extends Repository
{
    private const TABLE_NAME = 'tx_autotranslate_batch_item';

    protected $defaultOrderings = [
        'priority' => QueryInterface::ORDER_DESCENDING,
        'translate' => QueryInterface::ORDER_ASCENDING,
    ];

    public function initializeObject(): void
    {
        $querySettings = GeneralUtility::makeInstance(Typo3QuerySettings::class);
        $querySettings->setRespectStoragePage(false);
        $querySettings->setIgnoreEnableFields(true);
        $this->setDefaultQuerySettings($querySettings);
    }

    /**
     * Find all pages recursively for the selected page tree item
     */
    public function findAllRecursive(int $levels = 0, ?int $pageId = null): ?QueryResultInterface
    {
        if (!$pageId) {
            return null;
        }

        $pageIds = [$pageId];
        if ($levels > 0) {
            $pageIds = array_merge(
                $pageIds,
                PageUtility::getSubpageIds($pageIds[0], $levels - 1)
            );
        }

        return $this->findAllByPids($pageIds);
    }

    /**
     * Find all items by given page ids
     */
    public function findAllByPids(array $pids): ?QueryResultInterface
    {
        if (empty($pids)) {
            return null;
        }

        $query = $this->createQuery();
        $query->matching(
            $query->in('pid', $pids)
        );

        return $query->execute();
    }

    /**
     * Check if a pending (not yet finished, no error) batch item already exists
     * for the given page and target language.
     *
     * An item is considered "pending" if:
     * - It has no error (error is empty or NULL)
     * - It is not yet finished (translated IS NULL or translated <= translate)
     */
    public function hasPendingItem(int $pid, int $sysLanguageUid): bool
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable(self::TABLE_NAME);

        $queryBuilder->getRestrictions()->removeAll();

        $count = $queryBuilder
            ->count('uid')
            ->from(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($sysLanguageUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->eq('error', $queryBuilder->createNamedParameter('')),
                    $queryBuilder->expr()->isNull('error')
                ),
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->isNull('translated'),
                    $queryBuilder->expr()->lte('translated', $queryBuilder->quoteIdentifier('translate'))
                )
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();

        return (int)$count > 0;
    }

    /**
     * Find items with errors for a given page and target language.
     *
     * @return array<int, array{uid: int, pid: int, error: string}> List of errored items
     */
    public function findErroredItems(int $pid, int $sysLanguageUid): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable(self::TABLE_NAME);

        $queryBuilder->getRestrictions()->removeAll();

        return $queryBuilder
            ->select('uid', 'pid', 'error')
            ->from(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($sysLanguageUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->neq('error', $queryBuilder->createNamedParameter('')),
                $queryBuilder->expr()->isNotNull('error')
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }
}
