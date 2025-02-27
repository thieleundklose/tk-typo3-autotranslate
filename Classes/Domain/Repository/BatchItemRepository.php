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
    public function findAllRecursive(int $levels = 0, ?int $pageId = null)
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