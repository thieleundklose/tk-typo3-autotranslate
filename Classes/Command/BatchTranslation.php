<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use ThieleUndKlose\Autotranslate\Domain\Model\BatchItem;
use ThieleUndKlose\Autotranslate\Domain\Repository\BatchItemRepository;
use ThieleUndKlose\Autotranslate\Utility\LogUtility;
use ThieleUndKlose\Autotranslate\Utility\Records;
use ThieleUndKlose\Autotranslate\Utility\TranslationHelper;
use ThieleUndKlose\Autotranslate\Utility\Translator;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
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
            $res = $this->translate($item);
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

    /**
     * Translate the given item.
     * @param BatchItem $item
     * @return bool
     */
    protected function translate(BatchItem $item): bool
    {
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $siteConfiguration = $siteFinder->getSiteByPageId($item->getPid());
        $defaultLanguage = TranslationHelper::defaultLanguageFromSiteConfiguration($siteConfiguration);
        $languages = TranslationHelper::possibleTranslationLanguages($siteConfiguration->getLanguages());

        // check if target language is in pissible translation languages
        if (!isset($languages[$item->getSysLanguageUid()])) {
            $message = 'Target language ({targetLanguages}) not in site languages ({siteLanguages}).';
            $messageData = [
                'targetLanguages' => $item->getSysLanguageUid(),
                'siteLanguages' => implode(',', array_keys($languages)),
            ];

            LogUtility::log($this->logger, $message, $messageData, LogUtility::MESSAGE_ERROR);
            $item->setError(LogUtility::interpolate($message, $messageData));

            return false;
        }

        // check if page exists
        $pageRecord = Records::getRecord('pages', $item->getPid());
        if ($pageRecord === null) {
            LogUtility::log($this->logger, 'No page found ({pid}).', ['pid' => $item->getPid()], LogUtility::MESSAGE_WARNING);
            return false;
        }

        // init translation service
        $translator = GeneralUtility::makeInstance(Translator::class, $item->getPid());
        $translateAbleTables = TranslationHelper::translateableTables();
        foreach ($translateAbleTables as $table) {

            if ($table === 'pages') {
                // translate page
                $translator->translate($table, $item->getPid(), null, (string)$item->getSysLanguageUid(), $item->getMode());
            } else {
                // get and translate other content placed on page
                $records = Records::getRecords($table, 'uid', [
                    "pid = " . $item->getPid(),
                    "deleted = 0",
                    "sys_language_uid = " . $defaultLanguage->getLanguageId(),
                ]);
                foreach ($records as $uid) {
                    $translator->translate($table, $uid, null, (string)$item->getSysLanguageUid(), $item->getMode());
                }
            }
        }

        return true;
    }

}
