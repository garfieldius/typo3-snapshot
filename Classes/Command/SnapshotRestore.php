<?php
declare(strict_types=1);
namespace GrossbergerGeorg\Snapshot\Command;

/*
 * (c) 2019 Georg Großberger <contact@grossberger-ge.org>
 *
 * This file is free software; you can redistribute it and/or
 * modify it under the terms of the Apache License 2.0
 *
 * For the full copyright and license information see
 * <https://www.apache.org/licenses/LICENSE-2.0>
 */

use GrossbergerGeorg\Snapshot\ConsoleLogger;
use GrossbergerGeorg\Snapshot\Snapshot\RestoreDatabase;
use GrossbergerGeorg\Snapshot\Snapshot\RestoreFiles;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Command to restore an existing snapshot
 *
 * @author Georg Großberger <contact@grossberger-ge.org>
 */
class SnapshotRestore extends Command
{
    protected function configure()
    {
        $helpText = '
Restore a snapshot in var/snapshots/

Examples:

# Import latest with DB only
vendor/bin/typo3 snapshot:restore

# Import snapshot named "dev" including fileadmin data
vendor/bin/typo3 snapshot:restore dev -f
';

        $this->setDescription('Load and restore a snapshot from var/snapshot/');
        $this->setAliases(['restore']);
        $this->setHelp(trim($helpText));
        $this->addArgument('name', InputArgument::OPTIONAL, 'Name of the snapshot to restore');

        $this->addOption(
            'files',
            'f',
            InputOption::VALUE_NONE,
            'Restore fileadmin as well'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $name = $input->hasArgument('name') ? $input->getArgument('name') : null;
        $dir = Environment::getVarPath() . '/snapshot/';

        if (!$name) {
            $paths = GeneralUtility::get_dirs($dir);
            natcasesort($paths);
            $name = basename(array_pop($paths));
        }

        if (!is_dir($dir . $name)) {
            throw new \RuntimeException('No snapshot found');
        }

        $logger = GeneralUtility::makeInstance(ConsoleLogger::class, $output);
        $database = GeneralUtility::makeInstance(RestoreDatabase::class);
        $database->setDirectory($dir . $name);
        $database->setLogger($logger);
        $database->restore();

        if ($input->hasOption('files') && $input->getOption('files')) {
            $files = GeneralUtility::makeInstance(RestoreFiles::class);
            $files->setDirectory($dir . $name);
            $files->setLogger($logger);
            $files->restore();
        }
    }
}
