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

use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Information\Typo3Version;
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

        $queryBuilder
            ->select('uid')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pageId, ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(0, ParameterType::INTEGER))
            );

        $versionInformation = GeneralUtility::makeInstance(Typo3Version::class);
        if ($versionInformation->getMajorVersion() > 11) {
            $result = $queryBuilder->executeQuery();
        } else {
            $result = $queryBuilder->execute();
        }

        $rows = $result->fetchAllAssociative();
        foreach ($rows as $row) {
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
