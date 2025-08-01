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

namespace Think\Migration\Command;

use Phinx\Db\Adapter\AdapterFactory;
use Phinx\Db\Adapter\ProxyAdapter;
use Phinx\Migration\AbstractMigration;
use Phinx\Migration\MigrationInterface;
use Phinx\Util\Util;
use Think\Migration\Command;
use Think\Migration\Migrator;

abstract class Migrate extends Command
{
    /**
     * @var array
     */
    protected $migrations;

    protected function getPath()
    {
        return ROOT_PATH . 'database' . DIRECTORY_SEPARATOR . 'migrations';
    }

    protected function executeMigration(MigrationInterface $migration, $direction = MigrationInterface::UP)
    {
        $this->output->writeln('');
        $this->output->writeln(' ==' . ' <info>' . $migration->getVersion() . ' ' . $migration->getName() . ':</info>' . ' <comment>' . (MigrationInterface::UP === $direction ? L('migrating') : L('reverting')) . '</comment>');

        // Execute the migration and log the time elapsed.
        $start = microtime(true);

        $startTime = time();
        $direction = (MigrationInterface::UP === $direction) ? MigrationInterface::UP : MigrationInterface::DOWN;
        $migration->setAdapter($this->getAdapter());

        // begin the transaction if the adapter supports it
        if ($this->getAdapter()->hasTransactions()) {
            $this->getAdapter()->beginTransaction();
        }

        // Run the migration
        if (method_exists($migration, MigrationInterface::CHANGE)) {
            if (MigrationInterface::DOWN === $direction) {
                // Create an instance of the ProxyAdapter so we can record all
                // of the migration commands for reverse playback
                /** @var ProxyAdapter $proxyAdapter */
                $proxyAdapter = AdapterFactory::instance()->getWrapper('proxy', $this->getAdapter());
                $migration->setAdapter($proxyAdapter);
                /** @noinspection PhpUndefinedMethodInspection */
                $migration->change();
                $proxyAdapter->executeInvertedCommands();
                $migration->setAdapter($this->getAdapter());
            }
            else {
                /** @noinspection PhpUndefinedMethodInspection */
                $migration->change();
            }
        }
        else {
            $migration->{$direction}();
        }

        // commit the transaction if the adapter supports it
        if ($this->getAdapter()->hasTransactions()) {
            $this->getAdapter()->commitTransaction();
        }

        // Record it in the database
        $this->getAdapter()
            ->migrated($migration, $direction, date('Y-m-d H:i:s', $startTime), date('Y-m-d H:i:s', time()));

        $end  = microtime(true);
        $time = sprintf('%.4f', $end - $start);
        $this->output->writeln(' ==' . ' <info>' . $migration->getVersion() . ' ' . $migration->getName() . ':</info>' . ' <comment>' . (MigrationInterface::UP === $direction ? L('migrated, took {$seconds}s.', ['seconds' => $time]) : L('reverted, took {$seconds}s.', ['seconds' => $time])) . '</comment>');
    }

    protected function getVersionLog()
    {
        return $this->getAdapter()->getVersionLog();
    }

    protected function getVersions()
    {
        return $this->getAdapter()->getVersions();
    }

    protected function getMigrations()
    {
        if (null === $this->migrations) {
            $phpFiles = glob($this->getPath() . DIRECTORY_SEPARATOR . '*.php', defined('GLOB_BRACE') ? GLOB_BRACE : 0);

            // filter the files to only get the ones that match our naming scheme
            $fileNames = [];
            /** @var Migrator[] $versions */
            $versions = [];

            foreach ($phpFiles as $filePath) {
                if (Util::isValidMigrationFileName(basename($filePath))) {
                    $version = Util::getVersionFromFileName(basename($filePath));

                    if (isset($versions[$version])) {
                        throw new \InvalidArgumentException(L('Duplicate migration - "{$file}" has the same version as "{$ver}"', ['file' => $filePath, 'ver' => $versions[$version]->getVersion()]));
                    }

                    // convert the filename to a class name
                    $class = Util::mapFileNameToClassName(basename($filePath));

                    if (isset($fileNames[$class])) {
                        throw new \InvalidArgumentException(L('Migration "{$file}" has the same name as "{$class}"', ['file' => basename($filePath), 'class' => $fileNames[$class]]));
                    }

                    $fileNames[$class] = basename($filePath);

                    // load the migration file
                    /** @noinspection PhpIncludeInspection */
                    require_once $filePath;
                    if (!class_exists($class)) {
                        throw new \InvalidArgumentException(L('Could not find class "{$class}" in file "{$file}"', ['class' => $class, 'file' => $filePath]));
                    }

                    // instantiate it
                    $migration = new $class('', $version, $this->input, $this->output);

                    if (!($migration instanceof AbstractMigration)) {
                        throw new \InvalidArgumentException(L('The class "{$class}" in file "{$file}" must extend \Phinx\Migration\AbstractMigration', ['class' => $class, 'file' => $filePath]));
                    }

                    $versions[$version] = $migration;
                }
            }

            ksort($versions);
            $this->migrations = $versions;
        }

        return $this->migrations;
    }
}
