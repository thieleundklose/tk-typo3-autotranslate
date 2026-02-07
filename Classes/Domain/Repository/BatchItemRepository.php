<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Domain\Repository;

use ThieleUndKlose\Autotranslate\Utility\PageUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

final class BatchItemRepository extends Repository
{
    protected $defaultOrderings = [
        'priority' => QueryInterface::ORDER_DESCENDING,
        'translate' => QueryInterface::ORDER_ASCENDING,
    ];

    public function initializeObject(): void
    {
        $querySettings = GeneralUtility::makeInstance(Typo3QuerySettings::class);
        $querySettings->setRespectStoragePage(false);
        $querySettings->setIgnoreEnableFields(true);
        $this->setDefaultQuerySettings($querySettings);
    }

    /**
     * Find all pages recursively for the selected page tree item
     */
    public function findAllRecursive(int $levels = 0, ?int $pageId = null): ?QueryResultInterface
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
     * Find all items by given page ids
     */
    public function findAllByPids(array $pids): ?QueryResultInterface
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
