<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use ThieleUndKlose\Autotranslate\Domain\Repository\BatchItemRepository;
use ThieleUndKlose\Autotranslate\Service\BatchTranslationService;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

// final class BatchTranslation extends Command
final class BatchTranslation extends Command implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public const ITEMS_PER_RUN_DEFAULT = 1;

    /**
     * @var BatchItemRepository
     */
    protected $batchItemRepository;

    /**
     * @param BatchItemRepository $batchItemRepository
     * @return void
     */
    public function injectBatchItemRepository(BatchItemRepository $batchItemRepository): void
    {
        $this->batchItemRepository = $batchItemRepository;
    }

    /**
     * @var PersistenceManager
     * @inject
     */
    protected $persistenceManager;

    /**
     * @param PersistenceManager $persistenceManager
     * @return void
     */
    public function injectPersistenceManager(PersistenceManager $persistenceManager): void
    {
        $this->persistenceManager = $persistenceManager;
    }

    /**
     * @var BatchTranslationService
     * @inject
     */
    protected $batchTranslationService;

    /**
     * @param
     * @return void
     */
    public function injectBatchTranslationService(BatchTranslationService $batchTranslationService): void
    {
        $this->batchTranslationService = $batchTranslationService;
    }

    /**
     * @return void
     */

    protected function configure(): void
    {
        $this
            ->setDescription(
                'A command for automatic translation with feedback.
                Optionally add the number of translations which is otherwise 1' . self::ITEMS_PER_RUN_DEFAULT . 'per default.'
            )
            ->setHelp('Optionally add the number of translations which is otherwise ' . self::ITEMS_PER_RUN_DEFAULT . ' per default.')
            ->addArgument(
                'translationsPerRun',
                InputArgument::OPTIONAL,
                'Enter the number of translations you want to perform in a run. Default is ' . self::ITEMS_PER_RUN_DEFAULT . '.',
                self::ITEMS_PER_RUN_DEFAULT
            );
    }

    /**
     * Log the number of completed and failed translations.
     *
     * @param int $successfulTranslations
     * @param int $translationsPerRun
     * @return void
     */
    protected function logTranslationStats(int $successfulTranslations, int $translationsPerRun): void
    {
        $this->logger->info('{completed} Tasks completed and {failed} Tasks failed', [
            'completed' => $successfulTranslations,
            'failed' => $translationsPerRun - $successfulTranslations,
        ]);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $translationsPerRun = $input->getArgument('translationsPerRun');
        $translationsPerRun = (int)$translationsPerRun;
        $successfulTranslations = 0;

        $batchItemsToRun = $this->batchItemRepository->findWaitingForRun($translationsPerRun);

        foreach ($batchItemsToRun as $item) {
            $res = $this->batchTranslationService->translate($item);
            if ($res === true) {
                $successfulTranslations++;
                $item->markAsTranslated();
            }
            $this->batchItemRepository->update($item);
        }
        $this->persistenceManager->persistAll();

        $this->logTranslationStats($successfulTranslations, $translationsPerRun);

        $output->writeln($successfulTranslations . ' translation(s) completed successfully!');
        $output->writeln(($translationsPerRun - $successfulTranslations) . ' translation(s) failed.');
        return Command::SUCCESS;
    }



}
