<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Domain\Repository;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class LogRepository
{
    private const TABLE_NAME = 'tx_autotranslate_log';

    public function countAll(): int
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable(self::TABLE_NAME);

        $queryBuilder = $connection->createQueryBuilder();
        $requestIds = $queryBuilder
            ->select('request_id')
            ->from(self::TABLE_NAME)
            ->groupBy('request_id')
            ->executeQuery()
            ->fetchFirstColumn();

        return count($requestIds);
    }

    public function findAll(int $limit = 100): array
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable(self::TABLE_NAME);

        $queryBuilder = $connection->createQueryBuilder();
        $requestIds = $queryBuilder
            ->select('request_id')
            ->from(self::TABLE_NAME)
            ->groupBy('request_id')
            ->orderBy('time_micro', 'DESC')
            ->setMaxResults($limit)
            ->executeQuery()
            ->fetchFirstColumn();

        if (empty($requestIds)) {
            return [];
        }

        $queryBuilder = $connection->createQueryBuilder();

        return $queryBuilder
            ->select('*')
            ->from(self::TABLE_NAME)
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
    }

    public function deleteAll(): void
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable(self::TABLE_NAME);

        $connection->createQueryBuilder()
            ->delete(self::TABLE_NAME)
            ->executeStatement();
    }

    public function findByRequestId(string $requestId): array
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable(self::TABLE_NAME);

        $queryBuilder = $connection->createQueryBuilder();

        return $queryBuilder
            ->select('*')
            ->from(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->eq(
                    'request_id',
                    $queryBuilder->createNamedParameter($requestId)
                )
            )
            ->orderBy('time_micro', 'DESC')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    public function deleteByRequestId(string $requestId): void
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable(self::TABLE_NAME);

        $queryBuilder = $connection->createQueryBuilder();
        $queryBuilder
            ->delete(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->eq(
                    'request_id',
                    $queryBuilder->createNamedParameter($requestId)
                )
            )
            ->executeStatement();
    }
}
