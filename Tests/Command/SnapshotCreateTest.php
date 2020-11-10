<?php
declare(strict_types=1);
namespace GrossbergerGeorg\Snapshot\Tests\Command;

/*
 * (c) 2020 Georg Großberger <contact@grossberger-ge.org>
 *
 * This file is free software; you can redistribute it and/or
 * modify it under the terms of the Apache License 2.0
 *
 * For the full copyright and license information see
 * <https://www.apache.org/licenses/LICENSE-2.0>
 */

use GrossbergerGeorg\PHPDevTools\Testing\AbstractTestCase;
use GrossbergerGeorg\Snapshot\Command\SnapshotCreate;
use GrossbergerGeorg\Snapshot\Snapshot\CreateArchives;
use GrossbergerGeorg\Snapshot\Snapshot\CreateDatabase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\NullOutput;
use TYPO3\CMS\Core\Core\ApplicationContext;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * SnapshotCreateTest
 *
 * @author Georg Großberger <contact@grossberger-ge.org>
 */
class SnapshotCreateTest extends AbstractTestCase
{
    public function testExecuteUsesTimestampForNameIfNoneGiven()
    {
        $varPath = '/var';
        $pattern = 'ignored_pattern';

        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['snapshot']['ignoredTables'] = $pattern;
        $GLOBALS['EXEC_TIME'] = time();

        $name = date('YmdHis', $GLOBALS['EXEC_TIME']);
        $snapshotDir = $varPath . DIRECTORY_SEPARATOR . 'snapshot' . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR;

        Environment::initialize(
            new ApplicationContext('Testing'),
            true,
            true,
            __DIR__,
            __DIR__,
            $varPath,
            '',
            '',
            ''
        );

        $db = $this->makeMock(CreateDatabase::class);
        $db->expects(static::once())->method('generate');
        $db->expects(static::once())->method('setDirectory')->with(static::equalTo($snapshotDir));
        $db->expects(static::once())->method('enableAnonymizer');
        $db->expects(static::once())->method('setIgnoredTables')->with(static::equalTo($pattern));

        $tar = $this->makeMock(CreateArchives::class);
        $tar->expects(static::once())->method('generate');
        $tar->expects(static::once())->method('setDirectory')->with(static::equalTo($snapshotDir));
        $tar->expects(static::once())->method('setSmall')->with(static::equalTo(true));

        GeneralUtility::addInstance(CreateDatabase::class, $db);
        GeneralUtility::addInstance(CreateArchives::class, $tar);

        $subject = new SnapshotCreate();
        $input = new ArrayInput([
            '--files'     => true,
            '--small'     => true,
            '--anonymize' => true,
        ], $subject->getDefinition());

        $output = $this->makeMock(ConsoleOutput::class);
        $output->expects(static::once())->method('writeln')->with(static::equalTo($name));

        $subject->run($input, $output);
    }

    public function testExecute()
    {
        $name = 'test';
        $varPath = '/var';

        $snapshotDir = $varPath . DIRECTORY_SEPARATOR . 'snapshot' . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR;

        Environment::initialize(
            new ApplicationContext('Testing'),
            true,
            true,
            __DIR__,
            __DIR__,
            $varPath,
            '',
            '',
            ''
        );

        $pattern = 'ignored_pattern';
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['snapshot']['ignoredTables'] = $pattern;

        $db = $this->makeMock(CreateDatabase::class);
        $db->expects(static::once())->method('generate');
        $db->expects(static::once())->method('setDirectory')->with(static::equalTo($snapshotDir));
        $db->expects(static::once())->method('enableAnonymizer');
        $db->expects(static::once())->method('setIgnoredTables')->with(static::equalTo($pattern));

        $tar = $this->makeMock(CreateArchives::class);
        $tar->expects(static::once())->method('generate');
        $tar->expects(static::once())->method('setDirectory')->with(static::equalTo($snapshotDir));
        $tar->expects(static::once())->method('setSmall')->with(static::equalTo(true));

        GeneralUtility::addInstance(CreateDatabase::class, $db);
        GeneralUtility::addInstance(CreateArchives::class, $tar);

        $subject = new SnapshotCreate();
        $input = new ArrayInput([
            'name'        => $name,
            '--files'     => true,
            '--small'     => true,
            '--anonymize' => true,
        ], $subject->getDefinition());

        $output = new NullOutput();

        $subject->run($input, $output);
    }
}
