<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Command;

use DateTime;
use Doctrine\DBAL\ParameterType;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use ThieleUndKlose\Autotranslate\Domain\Model\BatchItem;
use ThieleUndKlose\Autotranslate\Service\BatchTranslationService;
use ThieleUndKlose\Autotranslate\Utility\LogUtility;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapper;

/**
 * CLI command for batch translation of queued items
 *
 * Usage: vendor/bin/typo3 autotranslate:batch:run [translationsPerRun]
 */
final class BatchTranslation extends Command implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const DEFAULT_ITEMS_PER_RUN = 1;
    private const TABLE_NAME = 'tx_autotranslate_batch_item';

    public function __construct(
        private readonly BatchTranslationService $batchTranslationService,
        private readonly ConnectionPool $connectionPool,
        private readonly DataMapper $dataMapper,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Processes queued translation tasks via DeepL API')
            ->setHelp('Translates a specified number of items from the batch queue. Default: ' . self::DEFAULT_ITEMS_PER_RUN)
            ->addArgument(
                'translationsPerRun',
                InputArgument::OPTIONAL,
                'Number of translations to process (default: ' . self::DEFAULT_ITEMS_PER_RUN . ')',
                self::DEFAULT_ITEMS_PER_RUN
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initializeBackendContext();

        $limit = (int)$input->getArgument('translationsPerRun');
        $items = $this->findPendingItems($limit);

        if (empty($items)) {
            $output->writeln('<info>No translations pending.</info>');
            return Command::SUCCESS;
        }

        $results = $this->processItems($items, $output);
        $this->logResults($results, $limit);
        $this->outputResults($output, $results, $limit);

        return Command::SUCCESS;
    }

    /**
     * Initialize backend context for CLI execution
     */
    private function initializeBackendContext(): void
    {
        if (PHP_SAPI !== 'cli') {
            return;
        }

        Bootstrap::initializeBackendAuthentication();
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest())
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
    }

    /**
     * Find items waiting to be translated
     */
    private function findPendingItems(int $limit): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_NAME);
        $queryBuilder->getRestrictions()->removeAll();

        $now = new DateTime();

        $result = $queryBuilder
            ->select('*')
            ->from(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->isNull('translated'),
                    $queryBuilder->expr()->gt('translate', 'translated')
                ),
                $queryBuilder->expr()->eq('error', $queryBuilder->createNamedParameter('')),
                $queryBuilder->expr()->lt('translate', $queryBuilder->createNamedParameter($now->getTimestamp())),
                $queryBuilder->expr()->eq('hidden', $queryBuilder->createNamedParameter(false))
            )
            ->setMaxResults($limit)
            ->executeQuery();

        return $this->dataMapper->map(BatchItem::class, $result->fetchAllAssociative());
    }

    /**
     * Process batch items and return success count
     */
    private function processItems(array $items, OutputInterface $output): int
    {
        $successCount = 0;

        foreach ($items as $item) {
            try {
                if ($this->translateItem($item)) {
                    $successCount++;
                    $output->writeln(sprintf('<info>✓ Translated item %d</info>', $item->getUid()));
                } else {
                    $output->writeln(sprintf('<error>✗ Failed to translate item %d</error>', $item->getUid()));
                }
            } catch (\Exception $e) {
                LogUtility::log(
                    $this->logger,
                    'Translation error for item {uid}: {error}',
                    ['uid' => $item->getUid(), 'error' => $e->getMessage()],
                    LogUtility::MESSAGE_ERROR
                );
                $output->writeln(sprintf('<error>✗ Error translating item %d: %s</error>', $item->getUid(), $e->getMessage()));
            }
        }

        return $successCount;
    }

    /**
     * Translate a single batch item
     */
    private function translateItem(BatchItem $item): bool
    {
        $success = $this->batchTranslationService->translate($item);

        if ($success) {
            $item->markAsTranslated();
        }

        $this->persistBatchItem($item);
        return $success;
    }

    /**
     * Persist batch item changes to database
     */
    private function persistBatchItem(BatchItem $item): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_NAME);

        $queryBuilder
            ->update(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($item->getUid(), ParameterType::INTEGER))
            )
            ->set('error', $item->getError())
            ->set('translate', $item->getTranslate()->getTimestamp());

        if ($item->getTranslated()) {
            $queryBuilder->set('translated', $item->getTranslated()->getTimestamp());
        }

        $queryBuilder->executeStatement();
    }

    /**
     * Log translation statistics
     */
    private function logResults(int $successCount, int $totalCount): void
    {
        $this->logger->info('Batch translation completed: {success} succeeded, {failed} failed', [
            'success' => $successCount,
            'failed' => $totalCount - $successCount,
        ]);
    }

    /**
     * Output results to console
     */
    private function outputResults(OutputInterface $output, int $successCount, int $totalCount): void
    {
        $failedCount = $totalCount - $successCount;

        $output->writeln('');
        $output->writeln(sprintf('<info>%d translation(s) completed successfully.</info>', $successCount));

        if ($failedCount > 0) {
            $output->writeln(sprintf('<error>%d translation(s) failed.</error>', $failedCount));
        }
    }
}
