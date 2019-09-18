<?php
declare(strict_types=1);
namespace GrossbergerGeorg\Snapshot\Tests\Command;

/*
 * (c) 2019 Georg Großberger <contact@grossberger-ge.org>
 *
 * This file is free software; you can redistribute it and/or
 * modify it under the terms of the Apache License 2.0
 *
 * For the full copyright and license information see
 * <https://www.apache.org/licenses/LICENSE-2.0>
 */

use GrossbergerGeorg\PHPDevTools\Testing\AbstractTestCase;
use GrossbergerGeorg\Snapshot\Command\SnapshotRestore;
use GrossbergerGeorg\Snapshot\Snapshot\RestoreDatabase;
use GrossbergerGeorg\Snapshot\Snapshot\RestoreFiles;
use org\bovigo\vfs\vfsStream;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use TYPO3\CMS\Core\Core\ApplicationContext;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * SnapshotRestoreTest
 *
 * @author Georg Großberger <contact@grossberger-ge.org>
 */
class SnapshotRestoreTest extends AbstractTestCase
{
    public function testRestoreErrorsSnapshotDoesNotExist()
    {
        $name = 'test';
        $fs = vfsStream::setup('project', null, [
            'var' => [
                'snapshot' => [
                    $name . '-other' => [
                        'Default.sql'         => 'empty',
                        '1--fileadmin.tar.gz' => 'empty',
                    ]
                ]
            ],
            'public' => [],
            'config' => [],
        ]);

        Environment::initialize(
            new ApplicationContext('Testing'),
            true,
            true,
            $fs->url(),
            $fs->getChild('public')->url(),
            $fs->getChild('var')->url(),
            $fs->getChild('config')->url(),
            '',
            ''
        );

        $subject = new SnapshotRestore();

        $input = new ArrayInput(['name' => $name], $subject->getDefinition());
        $output = new NullOutput();

        $this->expectException(\RuntimeException::class);

        $subject->run($input, $output);
    }

    public function testRestore()
    {
        $name = 'test';
        $fs = vfsStream::setup('project', null, [
            'var' => [
                'snapshot' => [
                    $name => [
                        'Default.sql'         => 'empty',
                        '1--fileadmin.tar.gz' => 'empty',
                    ]
                ]
            ],
            'public' => [],
            'config' => [],
        ]);

        Environment::initialize(
            new ApplicationContext('Testing'),
            true,
            true,
            $fs->url(),
            $fs->getChild('public')->url(),
            $fs->getChild('var')->url(),
            $fs->getChild('config')->url(),
            '',
            ''
        );

        $snapshotDir = $fs->getChild('var/snapshot/' . $name)->url();

        $db = $this->makeMock(RestoreDatabase::class);
        $db->expects(static::once())->method('setDirectory')->with(static::equalTo($snapshotDir));
        $db->expects(static::once())->method('restore');

        $files = $this->makeMock(RestoreFiles::class);
        $files->expects(static::once())->method('setDirectory')->with(static::equalTo($snapshotDir));
        $files->expects(static::once())->method('restore');

        GeneralUtility::addInstance(RestoreDatabase::class, $db);
        GeneralUtility::addInstance(RestoreFiles::class, $files);

        $subject = new SnapshotRestore();

        $input = new ArrayInput(['--files' => true], $subject->getDefinition());
        $output = new NullOutput();
        $subject->run($input, $output);
    }
}
