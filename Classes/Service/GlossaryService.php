<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Service;

use DeepL\Translator;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
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
        // Only consider glossary IDs that still exist in DeepL; local rows can outlive remote glossaries.
        $glossaries = $translator->listGlossaries();
        $glossaryIds = array_values(array_filter(
            array_map(static fn($glossary): string => (string)$glossary->glossaryId, $glossaries),
            static fn(string $glossaryId): bool => $glossaryId !== ''
        ));

        if (empty($glossaryIds)) {
            return null;
        }

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

        // deepltranslate-glossary stores language codes as lowercase two-letter values.
        $normalizedSourceLang = strtolower(substr($sourceLanguage, 0, 2));
        $normalizedTargetLang = strtolower(substr($targetLanguage, 0, 2));

        $where = $db->expr()->and(
            $db->expr()->eq('source_lang', $db->createNamedParameter($normalizedSourceLang)),
            $db->expr()->eq('target_lang', $db->createNamedParameter($normalizedTargetLang)),
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
