<?php
declare(strict_types=1);
namespace GrossbergerGeorg\Snapshot\Command;

/*
 * (c) 2020 Georg Großberger <contact@grossberger-ge.org>
 *
 * This file is free software; you can redistribute it and/or
 * modify it under the terms of the Apache License 2.0
 *
 * For the full copyright and license information see
 * <https://www.apache.org/licenses/LICENSE-2.0>
 */

use GrossbergerGeorg\Snapshot\ConsoleLogger;
use GrossbergerGeorg\Snapshot\Snapshot\CreateArchives;
use GrossbergerGeorg\Snapshot\Snapshot\CreateDatabase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Command to create a new snapshot
 *
 * @author Georg Großberger <contact@grossberger-ge.org>
 */
class SnapshotCreate extends Command
{
    protected function configure()
    {
        $helpText = '
Create a snapshot of the current installation inside the folder var/snapshots/
If no name is set, the current timestamp will be used and printed on stdout
so automated tools can read it.

If the name is given, there is no check if a snapshot with that is already present.
Any files in that snapshot will be overwritten.

Examples:

# Create a DB only snapshot
vendor/bin/typo3 snapshot:create

# Create a full snapshot including fileadmin,
# with the name "dev"
# anonymize data in user tables and
# create stubs for files larger than 100KB
# Useful for creating a development sample from a live installation
vendor/bin/typo3 snapshot:create dev -f -s -a
';

        $this->setDescription('Create a new snapshot in var/snapshot/');
        $this->setAliases(['snapshot', 'dump']);
        $this->setHelp(trim($helpText));
        $this->addArgument('name', InputArgument::OPTIONAL, 'Name of the snapshot to create');

        $this->addOption(
            'files',
            'f',
            InputOption::VALUE_NONE,
            'Save/restore fileadmin as well'
        );

        $this->addOption(
            'small',
            's',
            InputOption::VALUE_NONE,
            'Store stubs instead of actual content of files which are bigger than 100KB and '.
            'only add files which are used in sys_file_reference.'
        );

        $this->addOption(
            'anonymize',
            'a',
            InputOption::VALUE_NONE,
            'Anonymize records in DB'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->hasArgument('name') && $input->getArgument('name')) {
            $name = trim($input->getArgument('name'));
        } else {
            $name = date('YmdHis');
            $output->writeln($name, OutputInterface::VERBOSITY_NORMAL);
        }

        $directory = Environment::getVarPath() . DIRECTORY_SEPARATOR .
            'snapshot' . DIRECTORY_SEPARATOR .
            $name . DIRECTORY_SEPARATOR;

        $logger = GeneralUtility::makeInstance(ConsoleLogger::class, $output);
        $database = GeneralUtility::makeInstance(CreateDatabase::class);
        $database->setDirectory($directory);
        $database->setLog($logger);

        if ($input->hasOption('anonymize') && $input->getOption('anonymize')) {
            $database->enableAnonymizer();
        }

        if (is_string($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['snapshot']['ignoredTables'] ?? null)) {
            $database->setIgnoredTables($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['snapshot']['ignoredTables']);
        }

        $database->generate();

        if ($input->hasOption('files') && $input->getOption('files')) {
            $archive = GeneralUtility::makeInstance(CreateArchives::class);
            $archive->setDirectory($directory);
            $archive->setLog($logger);
            $archive->setSmall($input->hasOption('small') && $input->getOption('small'));
            $archive->generate();
        }
    }
}
