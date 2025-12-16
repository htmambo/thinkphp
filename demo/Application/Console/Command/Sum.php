<?php

namespace Demo;

use Think\Console\Command;
use Think\Console\Input;
use Think\Console\Input\Argument as InputArgument;
use Think\Console\Input\Option as InputOption;
use Think\Console\Output;

class Sum extends Command
{
    protected function configure()
    {
        $this->setName('demo:sum')
            ->setDefinition([
                new InputArgument('numbers', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'Numbers to sum'),
                new InputOption('round', 'r', InputOption::VALUE_OPTIONAL, 'Round result to N decimals'),
            ])
            ->setDescription('Demo: sum numbers');
    }

    protected function execute(Input $input, Output $output)
    {
        $numbers = (array)$input->getArgument('numbers');
        $sum = 0.0;

        foreach ($numbers as $value) {
            if (!is_numeric($value)) {
                $output->error('Invalid number: ' . $value);
                return 1;
            }

            $sum += (float)$value;
        }

        $round = $input->getOption('round');
        if ($round !== null && $round !== '') {
            if (!ctype_digit((string)$round)) {
                $output->error('Option --round must be an integer');
                return 1;
            }
            $sum = round($sum, (int)$round);
        }

        $output->info('Sum: ' . $sum);

        return 0;
    }
}
