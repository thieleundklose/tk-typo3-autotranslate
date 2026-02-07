<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Utility;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class PageUtility
{
    /**
     * Get all subpage ids of a given page id recursively
     */
    public static function getSubpageIds(int $pageId, int $levels = 0): array
    {
        $subpageIds = [];

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeAll();

        $result = $queryBuilder
            ->select('uid')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pageId, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->executeQuery();

        $rows = $result->fetchAllAssociative();
        foreach ($rows as $row) {
            $subpageIds[] = (int)$row['uid'];
        }

        if ($levels > 0) {
            foreach ($subpageIds as $subpageId) {
                $subpageIds = array_merge($subpageIds, self::getSubpageIds($subpageId, $levels - 1));
            }
        }

        return $subpageIds;
    }
}
