<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Domain\Repository;

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

use ThieleUndKlose\Autotranslate\Utility\PageUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

final class BatchItemRepository extends Repository {

    /**
     * @var array
     */
    protected $defaultOrderings = [
        'priority' => QueryInterface::ORDER_DESCENDING,
        'translate' => QueryInterface::ORDER_ASCENDING
    ];

    /**
     * @return void
     */
    public function initializeObject(): void
    {
        $querySettings = GeneralUtility::makeInstance(Typo3QuerySettings::class);
        $querySettings->setRespectStoragePage(false);
        $querySettings->setIgnoreEnableFields(true);
        $this->setDefaultQuerySettings($querySettings);
    }

    /**
     * find all pages recursively for actual given site from backend module selected tree item
     * @param int $levels
     * @param int|null $pageId
     * @return QueryResultInterface|array|null
     */
    public function findAllRecursive(int $levels = 0, int $pageId = null)
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
     * find all items recursively for actual given site from backend module selected tree item
     * @param int $limit|null
     * @return QueryResultInterface|array|null
     */
    public function findWaitingForRun(int $limit = null)
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_autotranslate_batch_item');
        $queryBuilder->getRestrictions()->removeAll();

        $now = new \DateTime();
        $queryBuilder
            ->select('uid')
            ->from('tx_autotranslate_batch_item')
            ->where(
                // only load items where translate is gerader than translated
                $queryBuilder->expr()->orX(
                    $queryBuilder->expr()->isNull('translated'),
                    $queryBuilder->expr()->gt('translate', 'translated'),
                ),
                // only load items where error is empty
                $queryBuilder->expr()->eq('error', $queryBuilder->createNamedParameter('')),
                // only loaditems with next translation date in the past
                $queryBuilder->expr()->lt('translate', $queryBuilder->createNamedParameter($now->getTimestamp())),
                // only load active items
                $queryBuilder->expr()->eq('hidden', $queryBuilder->createNamedParameter(false))
            );

            $versionInformation = GeneralUtility::makeInstance(Typo3Version::class);
            if ($versionInformation->getMajorVersion() < 11) {
                $statement = $queryBuilder->execute();
            } else {
                $statement = $queryBuilder->executeQuery();
            }

        $uids = $statement->fetchFirstColumn();

        return $this->findAllByUids($uids, $limit);
    }

    /**
     * find all items by given ids
     * @param array $uids
     * @param int $limit|null
     * @return QueryResultInterface|array|null
     */
    public function findAllByUids(array $uids, int $limit = null)
    {
        if (empty($uids)) {
            return [];
        }

        $query = $this->createQuery();
        $query->matching(
            $query->in('uid', $uids)
        );

        if ($limit !== null) {
            $query->setLimit($limit);
        }

        $versionInformation = GeneralUtility::makeInstance(Typo3Version::class);
        if ($versionInformation->getMajorVersion() > 11) {
            return $query->executeQuery();
        }
        return $query->execute();
    }

    /**
     * find all items by given page ids
     * @param array $pids
     * @return QueryResultInterface|array|null
     */
    public function findAllByPids(array $pids)
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

}