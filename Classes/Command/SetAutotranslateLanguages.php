<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * CLI command to set autotranslate_languages for existing records
 *
 * Usage examples:
 *   vendor/bin/typo3 autotranslate:set-languages tt_content 2,3
 *   vendor/bin/typo3 autotranslate:set-languages tt_content 2,3 --page=410
 *   vendor/bin/typo3 autotranslate:set-languages tt_content 2,3 --only-empty
 */
final class SetAutotranslateLanguages extends Command
{
    protected function configure(): void
    {
        $this
            ->setDescription('Set autotranslate_languages field for existing records')
            ->setHelp(
                'Updates the autotranslate_languages field for existing records in the specified table.' . PHP_EOL .
                'Only updates records in the default language (sys_language_uid = 0).' . PHP_EOL . PHP_EOL .
                'Examples:' . PHP_EOL .
                '  typo3 autotranslate:set-languages tt_content 2,3' . PHP_EOL .
                '  typo3 autotranslate:set-languages tt_content 2,3 --page=410' . PHP_EOL .
                '  typo3 autotranslate:set-languages tt_content 2,3 --only-empty'
            )
            ->addArgument(
                'table',
                InputArgument::REQUIRED,
                'Table name (e.g., tt_content, pages)'
            )
            ->addArgument(
                'languages',
                InputArgument::REQUIRED,
                'Comma-separated list of target language UIDs (e.g., 2,3)'
            )
            ->addOption(
                'page',
                'p',
                InputOption::VALUE_REQUIRED,
                'Only update records on this page ID'
            )
            ->addOption(
                'only-empty',
                null,
                InputOption::VALUE_NONE,
                'Only update records where autotranslate_languages is currently empty'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Show what would be updated without making changes'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $table = $input->getArgument('table');
        $languages = $input->getArgument('languages');
        $pageId = $input->getOption('page');
        $onlyEmpty = $input->getOption('only-empty');
        $dryRun = $input->getOption('dry-run');

        // Validate table exists and has autotranslate_languages column
        if (!isset($GLOBALS['TCA'][$table])) {
            $output->writeln(sprintf('<error>Table "%s" does not exist in TCA.</error>', $table));
            return Command::FAILURE;
        }

        if (!isset($GLOBALS['TCA'][$table]['columns']['autotranslate_languages'])) {
            $output->writeln(sprintf('<error>Table "%s" does not have the autotranslate_languages column.</error>', $table));
            return Command::FAILURE;
        }

        // Validate languages format
        $languageIds = GeneralUtility::trimExplode(',', $languages, true);
        if (empty($languageIds)) {
            $output->writeln('<error>No valid language IDs provided.</error>');
            return Command::FAILURE;
        }

        // Normalize the languages string
        $languagesNormalized = implode(',', $languageIds);

        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable($table);

        $queryBuilder = $connection->createQueryBuilder();
        $queryBuilder
            ->select('uid', 'pid', 'autotranslate_languages')
            ->from($table)
            ->where(
                $queryBuilder->expr()->eq('sys_language_uid', 0),
                $queryBuilder->expr()->eq('deleted', 0)
            );

        if ($pageId !== null) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->eq('pid', (int)$pageId)
            );
        }

        if ($onlyEmpty) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->isNull('autotranslate_languages'),
                    $queryBuilder->expr()->eq('autotranslate_languages', $queryBuilder->createNamedParameter(''))
                )
            );
        }

        $records = $queryBuilder->executeQuery()->fetchAllAssociative();
        $count = count($records);

        if ($count === 0) {
            $output->writeln('<info>No records found matching the criteria.</info>');
            return Command::SUCCESS;
        }

        if ($dryRun) {
            $output->writeln(sprintf('<info>[DRY-RUN] Would update %d record(s) in table "%s":</info>', $count, $table));
            foreach ($records as $record) {
                $output->writeln(sprintf(
                    '  - UID %d (pid: %d, current: "%s" → new: "%s")',
                    $record['uid'],
                    $record['pid'],
                    $record['autotranslate_languages'] ?? '',
                    $languagesNormalized
                ));
            }
            return Command::SUCCESS;
        }

        // Perform the update
        $updateQueryBuilder = $connection->createQueryBuilder();
        $updateQueryBuilder
            ->update($table)
            ->set('autotranslate_languages', $languagesNormalized)
            ->where(
                $updateQueryBuilder->expr()->eq('sys_language_uid', 0),
                $updateQueryBuilder->expr()->eq('deleted', 0)
            );

        if ($pageId !== null) {
            $updateQueryBuilder->andWhere(
                $updateQueryBuilder->expr()->eq('pid', (int)$pageId)
            );
        }

        if ($onlyEmpty) {
            $updateQueryBuilder->andWhere(
                $updateQueryBuilder->expr()->or(
                    $updateQueryBuilder->expr()->isNull('autotranslate_languages'),
                    $updateQueryBuilder->expr()->eq('autotranslate_languages', $updateQueryBuilder->createNamedParameter(''))
                )
            );
        }

        $affectedRows = $updateQueryBuilder->executeStatement();

        $output->writeln(sprintf(
            '<info>Successfully updated %d record(s) in table "%s" with autotranslate_languages = "%s"</info>',
            $affectedRows,
            $table,
            $languagesNormalized
        ));

        return Command::SUCCESS;
    }
}
