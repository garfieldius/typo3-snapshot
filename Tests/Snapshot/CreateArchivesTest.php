<?php
declare(strict_types=1);
namespace GrossbergerGeorg\Snapshot\Tests\Snapshot;

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
use GrossbergerGeorg\Snapshot\Snapshot\CreateArchives;
use org\bovigo\vfs\vfsStream;
use Psr\Log\NullLogger;
use Symfony\Component\Process\Process;
use TYPO3\CMS\Core\Core\ApplicationContext;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * CreateDatabaseTest
 *
 * @author Georg Großberger <contact@grossberger-ge.org>
 */
class CreateArchivesTest extends AbstractTestCase
{
    protected function tearDown(): void
    {
        GeneralUtility::resetSingletonInstances([]);
    }

    public function testGenerate()
    {
        $dataDir = dirname(__DIR__) . '/Data/';
        $root = vfsStream::setup('root', 0777, [
            'var' => [
                'snapshot' => [
                    'test' => [],
                ],
                'snapshot-1234567' => [
                    'storage-1' => [
                        'content' => [],
                    ],
                ],
            ],
            'public' => [
                'fileadmin' => [
                    'content' => [
                        'big.pdf'   => file_get_contents($dataDir . 'big.pdf'),
                        'big.png'   => file_get_contents($dataDir . 'big.png'),
                        'big.gif'   => file_get_contents($dataDir . 'big.gif'),
                        'big.jpg'   => file_get_contents($dataDir . 'big.jpg'),
                        'big.zip'   => file_get_contents($dataDir . 'big.zip'),
                        'big.webp'  => file_get_contents($dataDir . 'big.webp'),
                        'small.gif' => file_get_contents($dataDir . 'small.gif'),
                    ],
                    '_processed_' => [
                        'my_file.txt' => 'must be ignored',
                    ],
                ],
            ],
        ]);

        Environment::initialize(
            new ApplicationContext('Testing'),
            true,
            true,
            $root->url(),
            $root->getChild('public')->url(),
            $root->getChild('var')->url(),
            '',
            '',
            ''
        );

        $storageUid = 1;
        $storageName = 'test';
        $config = [
            'basePath' => 'fileadmin/',
            'pathType' => 'relative',
        ];

        $processedFolder = $this->makeMock(Folder::class);
        $processedFolder->expects(static::any())->method('getName')->willReturn('_processed_');

        $GLOBALS['TYPO3_CONF_VARS']['BE']['lockRootPath'] = '';
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['folderCreateMask'] = 0770;
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['fileCreateMask'] = 0660;
        $GLOBALS['TYPO3_CONF_VARS']['LOG'] = [];
        $GLOBALS['EXEC_TIME'] = 1234567;

        $storage = $this->makeMock(ResourceStorage::class);
        $storage->expects(static::any())->method('getUid')->willReturn($storageUid);
        $storage->expects(static::any())->method('getName')->willReturn($storageName);
        $storage->expects(static::any())->method('getConfiguration')->willReturn($config);
        $storage->expects(static::any())->method('getProcessingFolder')->willReturn($processedFolder);

        $proc = $this->makeMock(Process::class);
        $proc->expects(static::once())->method('mustRun');

        $qb = $this->makeMock(QueryBuilder::class);
        $qb->expects(static::once())->method('execute')->willReturn([
            ['identifier' => '/content/big.pdf'],
            ['identifier' => '/content/big.png'],
            ['identifier' => '/content/big.jpg'],
            ['identifier' => '/content/big.gif'],
            ['identifier' => '/content/big.zip'],
            ['identifier' => '/content/big.webp'],
            ['identifier' => '/content/small.gif'],
        ]);

        $pool = $this->makeMock(ConnectionPool::class);
        $pool->expects(static::once())
            ->method('getQueryBuilderForTable')
            ->with(static::equalTo('sys_file'))
            ->willReturn($qb);

        $storages = $this->makeMock(StorageRepository::class);
        $storages->expects(static::once())
            ->method('findByStorageType')
            ->with(static::equalTo('Local'))
            ->willReturn([$storage]);

        GeneralUtility::setSingletonInstance(StorageRepository::class, $storages);
        GeneralUtility::addInstance(Process::class, $proc);
        GeneralUtility::addInstance(ConnectionPool::class, $pool);

        $subject = new CreateArchives();
        $subject->setSmall(true);
        $subject->setLog(new NullLogger());
        $subject->setDirectory($root->getChild('var/snapshot/test')->url() . '/');
        $subject->generate();
    }
}
