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

class Records {

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

        if (GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion() > 10) {
            $res = $query->execute()->fetchAssociative();
        } else {
            $res = $query->execute()->fetch();
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

        if (GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion() > 10) {
            $res = $query->execute()->fetchAssociative();
        } else {
            $res = $query->execute()->fetch();
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
            ->where('uid=' . $uid);

        if ($properties !== null) {
            foreach ($properties as $property => $value) {
                if($value !== null) {
                    $update->set($property, $value);
                }
            }
        }
        $update->execute();
    }

    /**
     * Get all localized item uids by languages.
     *
     * @param string $table
     * @param int $uid
     * @return array|mixed[]
     * @throws Exception
     */
    public static function getLocalizedUids(string $table, int $uid): array
    {
        $queryBuilder = self::getQueryBuilder($table);

        $query = $queryBuilder->select('sys_language_uid','uid')
            ->from($table)
            ->where($queryBuilder->expr()->eq(TranslationHelper::translationOrigPointerField($table), $uid));

        if (GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion() > 10) {
            return $query->execute()->fetchAllKeyValue();
        } else {
            $res = $query->execute()->fetchAll();
        
            $resKeyValue = [];
            foreach ($res as $item) {
                $resKeyValue[$item['sys_language_uid']] = $item['uid'];
            }

            return $resKeyValue;
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
        $query = $queryBuilder->select($fields)
            ->from($table);

        foreach ($constraints as $constraint) {
            $query->andWhere($constraint);
        }

        if (GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion() > 10) {
            return $query->execute()->fetchFirstColumn();
        } else {
            return array_map(
                function ($item) {
                    return current($item);
                },
                $query->execute()->fetchAll()
            );
        }
    }

}
