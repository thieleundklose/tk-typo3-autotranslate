<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;

use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Log\Channel;

#[Channel('ThieleUndKlose.Autotranslate.Command.BatchTranslation')]
final class BatchTranslation extends Command
{

    /**
     * @return void
     */

    public function __construct(
        private readonly LoggerInterface $logger,
    )
    {
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
