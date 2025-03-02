<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Service;

use DeepL\Translator;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;
use WebVision\Deepltranslate\Glossary\Domain\Dto\Glossary;

final class GlossaryService
{

    /**
     * @param string $sourceLanguage
     * @param string $targetLanguage
     * @param integer $pageId
     * @param Translator $translator
     *
     * @return ?Glossary
     *
     * @throws Exception
     * @throws SiteNotFoundException
     * @throws \Doctrine\DBAL\Exception
     */
    public function getGlossary(
        string $sourceLanguage,
        string $targetLanguage,
        int $pageId,
        Translator $translator
    ): ?Glossary {

        if (!ExtensionManagementUtility::isLoaded('deepltranslate_glossary')) {
            return null;
        }

        // get existend glossary ids
        $glossaries = $translator->listGlossaries();
        $glossaryIds = array_map(fn($glossary) => $glossary->glossaryId, $glossaries);

        $db = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('pages');

        $result = $db
            ->select('uid')
            ->from('pages')
            ->where(
                $db->expr()->eq(
                    'doktype',
                    $db->createNamedParameter(
                        PageRepository::DOKTYPE_SYSFOLDER,
                        Connection::PARAM_INT
                    )
                ),
                $db->expr()->eq('module', $db->createNamedParameter('glossary'))
            )->executeQuery();

        $rows = $result->fetchAllAssociative();
        if (count($rows) === 0) {
            return null;
        }

        $rootPage = $this->findRootPageId($pageId);

        $ids = [];
        foreach ($rows as $row) {
            $glossaryRootPageID = $this->findRootPageId($row['uid']);

            if ($glossaryRootPageID !== $rootPage) {
                continue;
            }

            $ids[] = $row['uid'];
        }

        if (empty($ids)) {
            return null;
        }

        $db = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_deepltranslate_glossary');

        $pidConstraint = $db->expr()->in('pid', $ids);

        $where = $db->expr()->and(
            $db->expr()->eq('source_lang', $db->createNamedParameter($sourceLanguage)),
            $db->expr()->eq('target_lang', $db->createNamedParameter($targetLanguage)),
            $db->expr()->in('glossary_id', $db->createNamedParameter($glossaryIds, Connection::PARAM_STR_ARRAY)),
            $pidConstraint
        );

        $statement = $db
            ->select(
                'uid',
                'glossary_id',
                'glossary_name',
                'glossary_lastsync',
                'glossary_ready',
            )
            ->from('tx_deepltranslate_glossary')
            ->where($where)
            ->setMaxResults(1);
        $result = $statement->executeQuery()->fetchAssociative();
        return $result ? Glossary::fromDatabase($result) : null;
    }

    private function findRootPageId(int $pageId): int
    {
        $site = GeneralUtility::makeInstance(SiteFinder::class)->getSiteByPageId($pageId);
        return $site->getRootPageId();
    }
}
