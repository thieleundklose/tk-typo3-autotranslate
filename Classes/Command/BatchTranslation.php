<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;

use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Log\Channel;

final class BatchTranslation extends Command
{

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @return void
     */

    public function __construct()
    {
        $GLOBALS['TYPO3_CONF_VARS']['LOG']['ThieleUndKlose']['Autotranslate']['Command']['BatchTranslation'] = [
            'writerConfiguration' => [
                \Psr\Log\LogLevel::INFO => [
                    \TYPO3\CMS\Core\Log\Writer\DatabaseWriter::class => [
                        'logTable' => 'sys_log',
                    ],
                ],
            ],
        ];
        $this->logger = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Log\LogManager::class)->getLogger('ThieleUndKlose.Autotranslate.Command.BatchTranslation');
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription(
                'A command for automatic translation with feedback. 
                Optionally add the number of translations which is otherwise 1 per default.'
            )
            ->setHelp('Optionally add the number of translations which is otherwise 1 per default.')
            ->addArgument(
                'translationsPerRun',
                InputArgument::OPTIONAL,
                'Enter the number of translations you want to perform in a run. Default is 1.',
                '1'
            );
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
        $successfulTranslations = rand(0, $translationsPerRun);

        $this->logger->info('{completed} Tasks completed and {failed} Tasks failed', [
            'completed' => $successfulTranslations,
            'failed' => $translationsPerRun - $successfulTranslations,
        ]);

        // $GLOBALS['BE_USER']->writeLog(
        //     4,  // Type (4 = message)
        //     0,  // Action (0 = message)
        //     0,  // Error level (0 = OK)
        //     0,  // Details number
        //     '{completed} Tasks completed and {failed} Tasks failed',  // Details
        //     [
        //         'completed' => $successfulTranslations,
        //         'failed' => $translationsPerRun - $successfulTranslations,
        //     ],  // Data
        //     'channel' => 'test'
        // );

        $output->writeln($successfulTranslations . ' translation(s) completed successfully!');
        $output->writeln(($translationsPerRun - $successfulTranslations) . ' translation(s) failed.');
        return Command::SUCCESS;
    }
}
