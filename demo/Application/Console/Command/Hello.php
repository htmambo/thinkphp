<?php

namespace Demo;

use Think\Console\Command;
use Think\Console\Input;
use Think\Console\Input\Argument as InputArgument;
use Think\Console\Input\Option as InputOption;
use Think\Console\Output;

class Hello extends Command
{
    protected function configure()
    {
        $this->setName('demo:hello')
            ->setDefinition([
                new InputArgument('name', InputArgument::OPTIONAL, 'Who do you want to greet?', 'World'),
                new InputOption('yell', 'y', InputOption::VALUE_NONE, 'If set, outputs the greeting in uppercase'),
            ])
            ->setDescription('Demo: print a greeting');
    }

    protected function execute(Input $input, Output $output)
    {
        $name = (string)$input->getArgument('name');
        $message = "Hello, {$name}!";

        if ($input->getOption('yell')) {
            $message = strtoupper($message);
        }

        $output->info($message);
        $output->writeln('Run at: ' . date('c'));

        return 0;
    }
}
