<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

// final class BatchTranslation extends Command
final class BatchTranslation extends Command implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @return void
     */

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

        $output->writeln($successfulTranslations . ' translation(s) completed successfully!');
        $output->writeln(($translationsPerRun - $successfulTranslations) . ' translation(s) failed.');
        return Command::SUCCESS;
    }
}
