<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class Autotranslate extends Command
{
    protected function configure(): void
    {
        $this
        ->setHelp('Optionally add the number of translations which is otherwise 1 per default.')
        ->addArgument(
            'translationNumber',
            InputArgument::OPTIONAL,
            'A number for keeping track of the amount of translations',
            '1'
        )
        ->addOption(
            'translations',
            't',
            InputOption::VALUE_NONE,
            'A command for automatic translations',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $translate=(bool)$input->getOption('translations');
        if ($translate) {
            $output->writeln($input->getArgument('translationNumber'));
        }
        return Command::SUCCESS;
    }
}
