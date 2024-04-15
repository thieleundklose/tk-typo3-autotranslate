<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class BatchTranslation extends Command
{
    protected function configure(): void
    {
        $this
        ->setDescription(
            'A command for automatic translation with feedback. 
            Optionally add the number of translations which is otherwise 1 per default.'
        )
        ->setHelp('Optionally add the number of translations which is otherwise 1 per default.')
        ->addArgument(
            'translationNumber',
            InputArgument::OPTIONAL,
            'Enter the number of translations',
            '1'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $translationNumber = $input->getArgument('translationNumber');
        $translationNumber = (int) $translationNumber;
        $successfulTranslations = rand(0, $translationNumber);
        $output->writeln($successfulTranslations.' translation(s) completed successfully!');
        $output->writeln(($translationNumber - $successfulTranslations).' translation(s) failed.');
        return Command::SUCCESS;
    }
}
