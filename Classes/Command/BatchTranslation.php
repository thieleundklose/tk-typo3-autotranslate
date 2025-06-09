<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Command;

use Doctrine\DBAL\ParameterType;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use ThieleUndKlose\Autotranslate\Domain\Model\BatchItem;
use ThieleUndKlose\Autotranslate\Domain\Repository\BatchItemRepository;
use ThieleUndKlose\Autotranslate\Service\BatchTranslationService;
use ThieleUndKlose\Autotranslate\Utility\LogUtility;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapper;

// final class BatchTranslation extends Command
final class BatchTranslation extends Command implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public const ITEMS_PER_RUN_DEFAULT = 1;

    /**
     * @var BatchTranslationService
     */
    protected $batchTranslationService;

    /**
     * @var ConnectionPool
     */
    protected $connectionPool;

    /**
     * @var DataMapper
     */
    protected $dataMapper;

    /**
     * @var Typo3Version
     */
    protected $typo3Version;

    /**
     * @param BatchItemRepository $batchItemRepository
     * @param ConnectionPool $connectionPool
     * @param DataMapper $dataMapper
     * @param Typo3Version $typo3Version
     */
    public function __construct(
        BatchTranslationService $batchTranslationService,
        ConnectionPool $connectionPool,
        DataMapper $dataMapper,
        Typo3Version $typo3Version
    ) {
        parent::__construct();
        $this->batchTranslationService = $batchTranslationService;
        $this->connectionPool = $connectionPool;
        $this->dataMapper = $dataMapper;
        $this->typo3Version = $typo3Version;
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
        if (PHP_SAPI === 'cli') {
            echo 'Running from CLI, setting application type to BE' . PHP_EOL;
            $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest())->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
        }

        $translationsPerRun = (int)$input->getArgument('translationsPerRun');
        $successfulTranslations = 0;

        $batchItemsToRun = $this->findWaitingForRun($translationsPerRun);

        if (empty($batchItemsToRun)) {
            $output->writeln('No translation to run!');
            return Command::SUCCESS;
        }

        try {
            foreach ($batchItemsToRun as $item) {
                $res = $this->batchTranslationService->translate($item);
                if ($res === true) {
                    $successfulTranslations++;
                    $item->markAsTranslated();
                    $this->updateBatchItem($item);
                }
            }

        } catch (\Exception $e) {
            LogUtility::log($this->logger, 'Error during batch translation: {error}', ['error' => $e->getMessage()], LogUtility::MESSAGE_ERROR);
            $output->writeln('Error initializing translation service: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $this->logTranslationStats($successfulTranslations, $translationsPerRun);

        $output->writeln($successfulTranslations . ' translation(s) completed successfully!');
        $output->writeln(($translationsPerRun - $successfulTranslations) . ' translation(s) failed.');
        return Command::SUCCESS;
    }

    /**
     * find all items recursively to run
     * TODO: filter items recursively for given page from argument
     * @param int $limit|null
     * @return array
     */
    public function findWaitingForRun(?int $limit = null): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_autotranslate_batch_item');
        $queryBuilder->getRestrictions()->removeAll();

        $now = new \DateTime();
        $queryBuilder
            ->select('*')
            ->from('tx_autotranslate_batch_item')
            ->where(
                // only load items where translate is gerader than translated
                $queryBuilder->expr()->or(
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

        if ($limit) {
            $queryBuilder->setMaxResults($limit);
        }

        $statement = $queryBuilder->executeQuery();

        return $this->dataMapper->map(BatchItem::class, $statement->fetchAllAssociative());
    }

    /**
     * Update properties of proceeded batchItem
     *
     * @param BatchItem $batchItem
     * @return void
     */
    private function updateBatchItem(BatchItem $item): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_autotranslate_batch_item');
        $queryBuilder
            ->update('tx_autotranslate_batch_item')
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
}
