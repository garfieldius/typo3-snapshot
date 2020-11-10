<?php
declare(strict_types=1);
namespace GrossbergerGeorg\Snapshot\Tests\Snapshot;

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
use GrossbergerGeorg\Snapshot\Snapshot\RestoreDatabase;
use org\bovigo\vfs\vfsStream;
use Psr\Log\NullLogger;
use Symfony\Component\Process\Process;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * RestoreDatabaseTest
 *
 * @author Georg Großberger <contact@grossberger-ge.org>
 */
class RestoreDatabaseTest extends AbstractTestCase
{
    protected function setUp(): void
    {
        putenv('PATH=/bin' . PATH_SEPARATOR .'/sbin');
        $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default'] = [
            'user'     => 'typo3',
            'password' => 'typo3',
            'dbname'   => 'typo3',
        ];
    }

    public function testRestore()
    {
        $sql = [
            '',
            'First statement;',
            'Multiline',
            ' Statement;',
            ''
        ];

        $connectionName = 'Default';

        $fs = vfsStream::setup('test', null, [
            $connectionName . '.sql.gz' => gzcompress(implode("\n", $sql)),
            $connectionName . '.sql'    => implode("\n", $sql),
        ]);

        $cnx = $this->makeMock(Connection::class);
        $cnx->expects(static::atLeastOnce())->method('exec');

        $pool = $this->makeMock(ConnectionPool::class);
        $pool->expects(static::atLeastOnce())
            ->method('getConnectionByName')
            ->with(static::equalTo($connectionName))
            ->willReturn($cnx);

        $proc = $this->makeMock(Process::class);
        $proc->expects(static::any())->method('mustRun');

        GeneralUtility::addInstance(ConnectionPool::class, $pool);
        GeneralUtility::addInstance(Process::class, $proc);

        $subject = new RestoreDatabase();
        $subject->setLogger(new NullLogger());
        $subject->setDirectory($fs->url());
        $subject->restore();
    }
}
