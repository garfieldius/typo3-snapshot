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
use GrossbergerGeorg\Snapshot\Snapshot\RestoreFiles;
use org\bovigo\vfs\vfsStream;
use Psr\Log\NullLogger;
use Symfony\Component\Process\Process;
use TYPO3\CMS\Core\Core\ApplicationContext;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * RestoreFilesTest
 *
 * @author Georg Großberger <contact@grossberger-ge.org>
 */
class RestoreFilesTest extends AbstractTestCase
{
    public function testGenerate()
    {
        $storageId = '1';
        $fs = vfsStream::setup('project', null, [
            'public' => [
                'fileadmin' => []
            ],
            'var' => [
                'snapshot' => [
                    'test' => [
                        $storageId . '--fileadmin.tar.gz' => 'Fileadmin data',
                    ],
                ],
            ],
        ]);

        Environment::initialize(
            new ApplicationContext('Testing'),
            true,
            true,
            $fs->url(),
            $fs->getChild('public')->url(),
            $fs->getChild('var')->url(),
            '',
            '',
            ''
        );

        $storage = $this->makeMock(ResourceStorage::class);
        $storage->expects(static::any())
            ->method('getConfiguration')
            ->willReturn(['pathType' => 'relative', 'basePath' => 'fileadmin/']);
        $storage->expects(static::any())->method('getDriverType')->willReturn('Local');
        $resourceFactory = $this->makeMock(ResourceFactory::class);
        $resourceFactory->expects(static::once())
            ->method('getStorageObject')
            ->with(static::equalTo($storageId))
            ->willReturn($storage);

        $proc = $this->makeMock(Process::class);
        $proc->expects(static::once())->method('mustRun');

        GeneralUtility::setSingletonInstance(ResourceFactory::class, $resourceFactory);
        GeneralUtility::addInstance(Process::class, $proc);

        $subject = new RestoreFiles();
        $subject->setLogger(new NullLogger());
        $subject->setDirectory($fs->getChild('var/snapshot/test')->url());
        $subject->restore();
    }
}
