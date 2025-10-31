<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace ThieleUndKlose\Autotranslate\Utility;

use Doctrine\DBAL\Driver\Exception;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\EndTimeRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\StartTimeRestriction;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class Records
{

    /**
     * Get QueryBuilder for given or loaded table
     *
     * @param string $table tablename
     * @return QueryBuilder
     */
    public static function getQueryBuilder(string $table): QueryBuilder
    {

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()
            ->removeByType(HiddenRestriction::class)
            ->removeByType(StartTimeRestriction::class)
            ->removeByType(EndTimeRestriction::class);

        return $queryBuilder;
    }

    /**
     * Get whole record or optional one column from the record.
     *
     * @param string $table
     * @param int $uid
     * @param string|null $column
     * @return mixed|mixed[]|null
     * @throws Exception
     */
    public static function getRecord(string $table, int $uid, ?string $column = null)
    {
        $queryBuilder = self::getQueryBuilder($table);
        $query = $queryBuilder->select('*')
            ->from($table)
            ->where($queryBuilder->expr()->eq('uid', $uid));

        $versionInformation = GeneralUtility::makeInstance(Typo3Version::class);
        if ($versionInformation->getMajorVersion() > 11) {
            $res = $query->executeQuery()->fetchAssociative();
        } else {
            $res = $query->execute()->fetchAssociative();
        }

        if ($res === false) {
            return null;
        }

        if ($column !== null) {
            return $res[$column] ?? null;
        }

        return $res;
    }

    /**
     * Get record translation for a specified language.
     *
     * @param string $table
     * @param int $uid
     * @param int $langUid
     * @param string|null $column
     * @return mixed|mixed[]|null
     * @throws Exception
     */
    public static function getRecordTranslation(string $table, int $uid, int $langUid, ?string $column = null)
    {
        $parentField = $GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField'];
        $queryBuilder = self::getQueryBuilder($table);

        $query = $queryBuilder->select('*')
            ->from($table)
            ->where($queryBuilder->expr()->eq('sys_language_uid', $langUid))
            ->andWhere($queryBuilder->expr()->eq($parentField, $uid));

        $versionInformation = GeneralUtility::makeInstance(Typo3Version::class);
        if ($versionInformation->getMajorVersion() > 11) {
            $res = $query->executeQuery()->fetchAssociative();
        } else {
            $res = $query->execute()->fetchAssociative();
        }

        if ($res === false) {
            return null;
        }

        if ($column !== null) {
            return $res[$column] ?? null;
        }

        return $res;
    }

    /**
     * Update record fields.
     *
     * @param string $table
     * @param int $uid
     * @param array|null $properties
     * @return void
     */
    public static function updateRecord(string $table, int $uid, ?array $properties = null)
    {
        $queryBuilder = self::getQueryBuilder($table);
        $update = $queryBuilder
            ->update($table)
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter(
                        $uid,
                        \TYPO3\CMS\Core\Database\Connection::PARAM_INT
                    )
                )
            );

        if ($properties !== null) {
            foreach ($properties as $property => $value) {
                if ($value !== null) {
                    $update->set($property, $value);
                }
            }
        }
        $versionInformation = GeneralUtility::makeInstance(Typo3Version::class);
        if ($versionInformation->getMajorVersion() > 11) {
            $update->executeStatement();
        } else {
            $update->execute();
        }
    }

    /**
     * Get record fields by table and constraints.
     *
     * @param string $table
     * @param string $fields
     * @param array $constraints
     * @return array|mixed[]
     * @throws Exception
     */
    public static function getRecords(string $table, string $fields, array $constraints = []): array
    {
        $queryBuilder = self::getQueryBuilder($table);
        $fieldList = array_map(
            static fn(string $field): string => $queryBuilder->quoteIdentifier(trim($field)),
            GeneralUtility::trimExplode(',', $fields, true)
        );

        $query = $queryBuilder->select(...$fieldList)
            ->from($table);

        foreach ($constraints as $constraint) {
            $query->andWhere($constraint);
        }

        $versionInformation = GeneralUtility::makeInstance(Typo3Version::class);
        if ($versionInformation->getMajorVersion() > 11) {
            return $query->executeQuery()->fetchFirstColumn();
        } else {
            return $query->execute()->fetchFirstColumn();
        }
    }
}
