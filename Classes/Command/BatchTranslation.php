<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Command;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use ThieleUndKlose\Autotranslate\Service\BatchTranslationRunner;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;

/**
 * CLI command for batch translation of queued items
 *
 * Usage: vendor/bin/typo3 autotranslate:batch:run [translationsPerRun]
 */
final class BatchTranslation extends Command implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const DEFAULT_ITEMS_PER_RUN = 50;

    public function __construct(
        private readonly BatchTranslationRunner $runner,
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
        $result = $this->runner->processBatch($limit);

        if ($result['processed'] === 0) {
            $output->writeln('<info>No translations pending.</info>');
            return Command::SUCCESS;
        }

        $this->logResults($result['succeeded'], $limit);
        $this->outputResults($output, $result);

        return Command::SUCCESS;
    }

    private function initializeBackendContext(): void
    {
        if (PHP_SAPI !== 'cli') {
            return;
        }

        Bootstrap::initializeBackendAuthentication();
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest())
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
    }

    private function logResults(int $successCount, int $totalCount): void
    {
        $this->logger->info('Batch translation completed: {success} succeeded, {failed} failed', [
            'success' => $successCount,
            'failed' => $totalCount - $successCount,
        ]);
    }

    private function outputResults(OutputInterface $output, array $result): void
    {
        $output->writeln('');
        $output->writeln(sprintf(
            '<info>Batch run complete: %d processed, %d succeeded, %d failed, %d remaining in queue.</info>',
            $result['processed'],
            $result['succeeded'],
            $result['failed'],
            $result['remaining']
        ));

        if ($result['failed'] > 0) {
            $output->writeln(sprintf('<error>%d translation(s) failed. Check the batch items for error details.</error>', $result['failed']));
        }

        if ($result['remaining'] > 0) {
            $output->writeln(sprintf('<comment>%d item(s) still pending — will be processed in the next run.</comment>', $result['remaining']));
        }
    }
}
