<?php
namespace ThieleUndKlose\Autotranslate\Domain\Repository;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;

class LogRepository
{
    public function findAll(int $limit = 100): array
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
        ->getConnectionForTable('tx_autotranslate_log');

        // 1. the 100 latest request_id groups
        $queryBuilder = $connection->createQueryBuilder();
        $requestIds = $queryBuilder
            ->select('request_id')
            ->from('tx_autotranslate_log')
            ->groupBy('request_id')
            ->orderBy('time_micro', 'DESC')
            ->setMaxResults($limit)
            ->executeQuery()
            ->fetchFirstColumn();

        if (empty($requestIds)) {
            return [];
        }

        $queryBuilder = $connection->createQueryBuilder();

        // 2. all items of this group
        $rows = $queryBuilder
        ->select('*')
        ->from('tx_autotranslate_log')
        ->where(
            $queryBuilder->expr()->in(
                'request_id',
                $queryBuilder->createNamedParameter($requestIds, Connection::PARAM_STR_ARRAY)
            )
        )
        ->orderBy('time_micro', 'DESC')
        ->addOrderBy('request_id', 'DESC')
        ->executeQuery()
        ->fetchAllAssociative();

        return $rows;
    }

    public function findByRequestId(string $requestId): array
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_autotranslate_log');

        $queryBuilder = $connection->createQueryBuilder();
        $rows = $queryBuilder
            ->select('*')
            ->from('tx_autotranslate_log')
            ->where(
                $queryBuilder->expr()->eq(
                    'request_id',
                    $queryBuilder->createNamedParameter($requestId)
                )
            )
            ->orderBy('time_micro', 'DESC')
            ->executeQuery()
            ->fetchAllAssociative();

        return $rows;
    }

    public function deleteByRequestId(string $requestId): void
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_autotranslate_log');

        $queryBuilder = $connection->createQueryBuilder();
        $queryBuilder
            ->delete('tx_autotranslate_log')
            ->where(
                $queryBuilder->expr()->eq(
                    'request_id',
                    $queryBuilder->createNamedParameter($requestId)
                )
            )
            ->executeStatement();
    }
}
