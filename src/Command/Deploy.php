<?php

namespace Mygento\Deployer\Command;

use Mygento\Deployer\Model\Workflow;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Deploy extends Command
{
    protected function configure()
    {
        $this
            ->setName('deploy:deploy')
            ->setDescription('Workflow build, deploy, release')
            ->setDefinition([
                new InputArgument('environment', InputArgument::OPTIONAL, 'Environment'),
                new InputOption('config_file', null, InputOption::VALUE_OPTIONAL, 'config file', null),
            ]);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filename = '.workflow.yaml';
        if ($input->getOption('config_file')) {
            $filename = $input->getOption('config_file');
        }

        $executor = new Workflow($output);
        $executor->deploy($filename);

        return Command::SUCCESS;
    }
}
