<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use ThieleUndKlose\Autotranslate\Utility\PageUtility;
use ThieleUndKlose\Autotranslate\Utility\Records;
use ThieleUndKlose\Autotranslate\Utility\SlugUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class RepairPageSlugs extends Command
{
    protected function configure(): void
    {
        $this
            ->setDescription('Regenerate default-language page slugs and repair translated parent paths.')
            ->addArgument('pageIds', InputArgument::REQUIRED, 'Comma-separated default-language page IDs.')
            ->addOption('languages', null, InputOption::VALUE_REQUIRED, 'Comma-separated target language IDs to repair; default-language slugs are always regenerated.')
            ->addOption('levels', null, InputOption::VALUE_REQUIRED, 'Number of descendant levels (0 means only the selected pages).', '0')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show planned changes without saving them.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $pageIds = $this->parseIds((string)$input->getArgument('pageIds'));
        $languageOption = $input->getOption('languages');
        $languageIds = $languageOption === null ? [] : $this->parseIds((string)$languageOption);
        $levels = (string)$input->getOption('levels');
        if ($pageIds === null || ($languageOption !== null && $languageIds === null) || !ctype_digit($levels)) {
            $output->writeln('<error>Use positive numeric page/language IDs and a non-negative level count.</error>');
            return Command::FAILURE;
        }

        $prefixParentPageSlug = (bool)($GLOBALS['TCA']['pages']['columns']['slug']['config']['generatorOptions']['prefixParentPageSlug'] ?? false);

        if (PHP_SAPI === 'cli') {
            Bootstrap::initializeBackendAuthentication();
            $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest())->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
        }

        usort($pageIds, static function (int $a, int $b): int {
            return count(BackendUtility::BEgetRootLine($a)) <=> count(BackendUtility::BEgetRootLine($b));
        });

        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $sourcePages = [];
        foreach ($pageIds as $pageId) {
            $page = Records::getRecord('pages', $pageId);
            if ($page === null || (int)$page['sys_language_uid'] !== 0) {
                $output->writeln(sprintf('<error>Page %d is missing or is not in the default language.</error>', $pageId));
                return Command::FAILURE;
            }
            $site = $siteFinder->getSiteByPageId($pageId);
            $siteId = $site->getIdentifier();
            $ids = [$pageId];
            if ((int)$levels > 0) {
                $ids = array_merge($ids, PageUtility::getSubpageIds($pageId, (int)$levels - 1));
            }
            foreach ($ids as $id) {
                if (!isset($sourcePages[$id]) && $siteFinder->getSiteByPageId((int)$id)->getIdentifier() === $siteId) {
                    $sourcePages[$id] = (string)Records::getRecord('pages', (int)$id, 'slug');
                }
            }
        }

        $dryRun = (bool)$input->getOption('dry-run');
        $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(self::class);
        $previousSlugs = [];
        $plannedSlugs = [];
        $plannedSourceSlugs = [];
        $sourceChanges = 0;
        $translationChanges = 0;
        foreach (array_keys($sourcePages) as $pageId) {
            $page = Records::getRecord('pages', (int)$pageId);
            if ($page === null) {
                continue;
            }

            $sourceSlug = SlugUtility::generateSlug($page, 'pages', 'slug');
            if ($sourceSlug !== null && isset($plannedSourceSlugs[(int)$page['pid']])) {
                $parentSlug = (string)Records::getRecord('pages', (int)$page['pid'], 'slug');
                $sourceSlug = SlugUtility::replaceParentPrefix(
                    $sourceSlug,
                    $plannedSourceSlugs[(int)$page['pid']],
                    [$parentSlug]
                ) ?? $sourceSlug;
            }
            if ($sourceSlug !== null && $sourceSlug !== (string)$page['slug']) {
                $output->writeln(sprintf('Page %d, default language: %s -> %s%s', $pageId, $page['slug'], $sourceSlug, $dryRun ? ' (dry run)' : ''));
                if (!$dryRun) {
                    SlugUtility::updatePageSlug((int)$pageId, $sourceSlug);
                    $sourceSlug = (string)Records::getRecord('pages', (int)$pageId, 'slug');
                }
                $sourceChanges++;
            }
            $plannedSourceSlugs[(int)$pageId] = $sourceSlug ?? (string)$page['slug'];

            if (!$prefixParentPageSlug || (int)$page['pid'] <= 0 || !empty($page['is_siteroot'])) {
                continue;
            }
            $parent = Records::getRecord('pages', (int)$page['pid']);
            if ($parent === null) {
                continue;
            }

            $site = $siteFinder->getSiteByPageId((int)$pageId);
            $availableLanguages = array_map(static function ($language): int {
                return $language->getLanguageId();
            }, $site->getLanguages());
            $languages = $languageIds === [] ? $availableLanguages : array_intersect($languageIds, $availableLanguages);
            foreach ($languages as $languageId) {
                if ($languageId <= 0) {
                    continue;
                }
                $localizedPage = Records::getRecordTranslation('pages', (int)$pageId, $languageId);
                if ($localizedPage === null) {
                    continue;
                }

                $parentTranslation = Records::getRecordTranslation('pages', (int)$parent['uid'], $languageId);
                if ($parentTranslation === null && empty($parent['is_siteroot'])) {
                    $output->writeln(sprintf('Skipped page %d, language %d: translated parent is missing.', $pageId, $languageId));
                    $logger->warning('Skipped slug repair for page {pageId}, language {languageId}: translated parent is missing.', [
                        'pageId' => $pageId,
                        'languageId' => $languageId,
                    ]);
                    continue;
                }

                $parentSlug = $plannedSlugs[(int)$parent['uid']][$languageId]
                    ?? ($parentTranslation['slug'] ?? '/');
                $oldParentSlugs = [
                    (string)$parent['slug'],
                    $sourcePages[(int)$parent['uid']] ?? (string)$parent['slug'],
                    $previousSlugs[(int)$parent['uid']][$languageId] ?? (string)$parentSlug,
                ];
                $currentSlug = (string)$localizedPage['slug'];
                $previousSlugs[(int)$pageId][$languageId] = $currentSlug;
                $newSlug = SlugUtility::replaceParentPrefix($currentSlug, (string)$parentSlug, $oldParentSlugs);
                if ($newSlug === null) {
                    continue;
                }

                $plannedSlugs[(int)$pageId][$languageId] = $newSlug;
                $output->writeln(sprintf('Page %d, language %d: %s -> %s%s', $pageId, $languageId, $currentSlug, $newSlug, $dryRun ? ' (dry run)' : ''));
                if (!$dryRun) {
                    SlugUtility::updatePageSlug((int)$localizedPage['uid'], $newSlug);
                    $plannedSlugs[(int)$pageId][$languageId] = (string)Records::getRecord('pages', (int)$localizedPage['uid'], 'slug');
                }
                $translationChanges++;
            }
        }

        $output->writeln(sprintf('%d default-language slug(s) regenerated, %d translated slug(s) repaired%s; EXT:redirects may also update descendants.', $sourceChanges, $translationChanges, $dryRun ? ' (dry run)' : ''));
        $logger->info('Slug repair completed: {sourceChanges} source slugs and {translationChanges} translated slugs {action}.', [
            'sourceChanges' => $sourceChanges,
            'translationChanges' => $translationChanges,
            'action' => $dryRun ? 'planned' : 'completed',
        ]);
        return Command::SUCCESS;
    }

    private function parseIds(string $value): ?array
    {
        $ids = [];
        foreach (explode(',', $value) as $part) {
            $part = trim($part);
            if ($part === '' || !ctype_digit($part) || (int)$part <= 0) {
                return null;
            }
            $ids[] = (int)$part;
        }
        return array_values(array_unique($ids));
    }
}
