<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CreateUserCommand extends Command
{
    protected static $defaultName = 'app:create-user';

    protected $requirePassword = false;

    public function __construct(bool $requirePassword = false){

        $this->requirePassword = $requirePassword;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('create a new user')
            ->setHelp("This command allows you to create a user...")
            ->addArgument('username', InputArgument::REQUIRED, 'Username')
            ->addArgument('password', $this->requirePassword ? InputArgument::REQUIRED : InputArgument::OPTIONAL, 'User password')
            ->addOption('option1', null, InputOption::VALUE_NONE, 'Aucune option')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $password = $input->getArgument('password');

        if ($password) {
            $io->note(sprintf('You passed an argument: %s', $password));
        }

        // if ($input->getOption('option1')) {
        //     // ...
        // }

        $output->writeln([
            'User Creator',
            '============',
            '',
        ]);

        //$output->writeln($this->someMethod());
        $output->writeln($input->getArgument('username'));

        $io->success('You are about to create a user');

        return Command::SUCCESS;
    }
}
