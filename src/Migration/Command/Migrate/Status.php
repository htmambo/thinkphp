<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2014 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace Think\Migration\Command\Migrate;

use Think\Console\Input\Option as InputOption;
use Think\Console\Input;
use Think\Console\Output;
use Think\Migration\Command\Migrate;
use Think\Console\Table as ConsoleTable;

class Status extends Migrate
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('migrate:status')
            ->setDefinition($this->createDefinition())
            ->setDescription(L('Show migration status'))
            ->addOption('--format', '-f', InputOption::VALUE_REQUIRED, 'The output format: text or json. Defaults to text.')
            ->setHelp(<<<EOT
The <info>migrate:status</info> command prints a list of all migrations, along with their current status

<info>php think migrate:status</info>
<info>php think migrate:status -f json</info>
EOT
            );
    }

    /**
     * Show the migration status.
     *
     * @param Input $input
     * @param Output $output
     * @return integer 0 if all migrations are up, or an error code
     */
    protected function execute(Input $input, Output $output)
    {
        $format = $input->getOption('format');

        if (null !== $format) {
            $output->writeln('<info>using format</info> ' . $format);
        }

        // print the status
        return $this->printStatus($format);
    }

    protected function printStatus($format = null)
    {
        $tObj = new ConsoleTable();
        $output     = $this->output;
        $migrations = [];
        $header = [
            'status' => L('Status'),
            'id' => L('Migration ID'),
            'started' => L('Started'),
            'finished' => L('Finished'),
            'name' => L('Migration Name')
        ];
        $rows = [];
        if (count($this->getMigrations())) {
            // TODO - rewrite using Symfony Table Helper as we already have this library
            // included and it will fix formatting issues (e.g drawing the lines)
            $output->writeln('');

            $versions      = $this->getVersionLog();

            foreach ($this->getMigrations() as $migration) {
                $field = [];
                $version = array_key_exists($migration->getVersion(), $versions) ? $versions[$migration->getVersion()] : false;
                if ($version) {
                    $status = '     <info>up</info> ';
                }
                else {
                    $status = '   <error>down</error> ';
                }
                $field['status'] = $status;
                $field['id'] = $migration->getVersion();
                $field['started'] = $version['start_time'];
                $field['finished'] = $version['end_time'];
                $field['name'] = '<comment>' . $migration->getName() . '</comment>';

                if ($version && $version['breakpoint']) {
                    $field['name'] .= '  <error>' . L('Breakpoint Set') . '</error>';
                }
                $rows[] = $field;

                $migrations[] = [
                    'migration_status' => trim(strip_tags($status)),
                    'migration_id' => sprintf('%14.0f', $migration->getVersion()),
                    'migration_name' => $migration->getName()
                ];
                unset($versions[$migration->getVersion()]);
            }

            if (count($versions)) {
                foreach ($versions as $missing => $version) {
                    $field = [];
                    $field['status'] = '<error>up</error>';
                    $field['id'] = $missing;
                    $field['started'] = $version['start_time'];
                    $field['finished'] = $version['end_time'];
                    $field['name'] = '<comment>' . $version['migration_name'] . '</comment>  <error>** MISSING **</error>';

                    if ($version && $version['breakpoint']) {
                        $field['name'] .= '  <error>' . L('Breakpoint Set') . '</error>';
                    }
                    $rows[] = $field;
                }
            }
            $tObj->setStyle('box');
            $tObj->setHeader($header, ConsoleTable::ALIGN_CENTER);
            $tObj->setRows($rows);
            $this->output->writeln(trim($tObj->render()));

        }
        else {
            // there are no migrations
            $output->writeln('');
            $output->writeln('There are no available migrations. Try creating one using the <info>create</info> command.');
        }

        // write an empty line
        $output->writeln('');
        if ($format !== null) {
            switch ($format) {
                case 'json':
                    $output->writeln(json_encode([
                        'pending_count' => count($this->getMigrations()),
                        'migrations' => $migrations
                    ]));
                    break;
                default:
                    $output->writeln('<info>Unsupported format: ' . $format . '</info>');
            }
        }
    }
}
