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

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class PageUtility
{

    /**
     * Get all subpage ids of a given page id recursively
     *
     * @param int $pageId
     * @param int $levels
     * @return array
     */
    public static function getSubpageIds(int $pageId, int $levels = 0): array
    {
        $subpageIds = [];

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeAll();

        $statement = $queryBuilder
            ->select('uid')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pageId, \PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT))
            )
            ->execute();
        while ($row = $statement->fetch()) {
            $subpageIds[] = $row['uid'];
        }

        if ($levels > 0) {
            foreach ($subpageIds as $subpageId) {
                $subpageIds = array_merge($subpageIds, self::getSubpageIds($subpageId, $levels - 1));
            }
        }

        return $subpageIds;
    }
}
