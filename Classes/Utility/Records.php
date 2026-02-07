<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Utility;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\EndTimeRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\StartTimeRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Utility class for database record operations
 */
final class Records
{
    /**
     * Get a record or a single column value from a record
     *
     * @return mixed|array|null Record array, single column value, or null if not found
     */
    public static function getRecord(string $table, int $uid, ?string $column = null): mixed
    {
        $queryBuilder = self::getQueryBuilder($table);

        $result = $queryBuilder
            ->select('*')
            ->from($table)
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)
                )
            )
            ->executeQuery()
            ->fetchAssociative();

        if ($result === false) {
            return null;
        }

        return $column !== null ? ($result[$column] ?? null) : $result;
    }

    /**
     * Get QueryBuilder for given table with relaxed restrictions
     */
    public static function getQueryBuilder(string $table): QueryBuilder
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable($table);

        $queryBuilder->getRestrictions()
            ->removeByType(HiddenRestriction::class)
            ->removeByType(StartTimeRestriction::class)
            ->removeByType(EndTimeRestriction::class);

        return $queryBuilder;
    }

    /**
     * Get a translated record for a specified language
     *
     * @return mixed|array|null Record array, single column value, or null if not found
     */
    public static function getRecordTranslation(string $table, int $uid, int $langUid, ?string $column = null): mixed
    {
        $parentField = $GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField'] ?? null;

        if ($parentField === null) {
            return null;
        }

        $queryBuilder = self::getQueryBuilder($table);

        $result = $queryBuilder
            ->select('*')
            ->from($table)
            ->where(
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($langUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq($parentField, $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchAssociative();

        if ($result === false) {
            return null;
        }

        return $column !== null ? ($result[$column] ?? null) : $result;
    }

    /**
     * Update record fields
     */
    public static function updateRecord(string $table, int $uid, ?array $properties = null): void
    {
        if ($properties === null || empty($properties)) {
            return;
        }

        $queryBuilder = self::getQueryBuilder($table);
        $update = $queryBuilder
            ->update($table)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT))
            );

        foreach ($properties as $property => $value) {
            if ($value !== null) {
                $update->set($property, $value);
            }
        }

        $update->executeStatement();
    }

    /**
     * Get record field values by table and constraints
     *
     * @param string $table Table name
     * @param string $fields Comma-separated field names
     * @param array $constraints Array of WHERE constraints
     * @return array First column values of matching records
     */
    public static function getRecords(string $table, string $fields, array $constraints = []): array
    {
        $queryBuilder = self::getQueryBuilder($table);
        $fieldList = GeneralUtility::trimExplode(',', $fields, true);

        $query = $queryBuilder
            ->select(...$fieldList)
            ->from($table);

        foreach ($constraints as $constraint) {
            $query->andWhere($constraint);
        }

        return $query->executeQuery()->fetchFirstColumn();
    }
}
