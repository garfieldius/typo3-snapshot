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

use Doctrine\DBAL\Portability\Statement;
use Doctrine\DBAL\Schema\MySqlSchemaManager;
use Doctrine\DBAL\Schema\Table;
use GrossbergerGeorg\PHPDevTools\Testing\AbstractTestCase;
use GrossbergerGeorg\Snapshot\Anonymize\Anonymizer;
use GrossbergerGeorg\Snapshot\Snapshot\CreateDatabase;
use org\bovigo\vfs\vfsStream;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * CreateDatabaseTest
 *
 * @author Georg Großberger <contact@grossberger-ge.org>
 */
class CreateDatabaseTest extends AbstractTestCase
{
    /**
     * @var string
     */
    private $dir;

    public function testGenerate()
    {
        $connectionName = 'Default';
        $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections'][$connectionName] = [];

        $dataTable = new Table('tt_content');
        $cacheTable = new Table('cf_pages');

        $schemaManager = $this->makeMock(MySqlSchemaManager::class);
        $schemaManager->expects(static::once())->method('listTables')->willReturn([$dataTable, $cacheTable]);

        $dataSchemaRecords = [
            [
                'Table'        => 'tt_content',
                'Create Table' => 'CREATE TABLE `tt_content` (
  `uid` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pid` int(11) NOT NULL DEFAULT 0,
  `header` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'\',
  PRIMARY KEY (`uid`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            ]
        ];

        $cacheSchemaRecords = [
            [
                'Table'        => 'cf_cache_pages',
                'Create Table' => 'CREATE TABLE `cf_cache_pages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `identifier` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'\',
  `expires` int(10) unsigned NOT NULL DEFAULT 0,
  `content` longblob DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cache_id` (`identifier`(180),`expires`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            ]
        ];

        $dataRecords = [
            [
                'uid'    => 1,
                'pid'    => 2,
                'header' => 'Content Element',
                'bodyt'  => null,
            ]
        ];

        $dataSchemaResult = $this->makeMock(Statement::class);
        $dataSchemaResult->expects(static::any())->method('fetchAll')->willReturn($dataSchemaRecords);

        $cacheSchemaResult = $this->makeMock(Statement::class);
        $cacheSchemaResult->expects(static::any())->method('fetchAll')->willReturn($cacheSchemaRecords);

        $dataRecordsResult = $this->makeMock(Statement::class);
        $dataRecordsResult->expects(static::any())->method('getIterator')->willReturn(new \ArrayObject($dataRecords));

        $cnx = $this->makeMock(Connection::class);
        $cnx->expects(static::once())->method('getSchemaManager')->willReturn($schemaManager);
        $cnx->expects(static::once())->method('select')->willReturn($dataRecordsResult);
        $cnx->expects(static::any())->method('exec');
        $cnx->expects(static::any())->method('quote')->willReturnCallback(function ($value) {
            return "'${value}'";
        });
        $cnx->expects(static::atLeastOnce())->method('query')->willReturnMap([
            ['SHOW CREATE TABLE `' . $dataTable->getName() . '`', $dataSchemaResult],
            ['SHOW CREATE TABLE `' . $cacheTable->getName() . '`', $cacheSchemaResult],
        ]);

        $pool = $this->makeMock(ConnectionPool::class);
        $pool->expects(static::any())
            ->method('getConnectionByName')
            ->with(static::equalTo($connectionName))
            ->willReturn($cnx);

        $anonymizer = $this->makeMock(Anonymizer::class);
        $anonymizer->expects(static::once())
            ->method('clearRecord')
            ->with(static::equalTo($dataTable->getName()), static::equalTo($dataRecords[0]));

        GeneralUtility::addInstance(ConnectionPool::class, $pool);
        GeneralUtility::addInstance(Anonymizer::class, $anonymizer);

        $dir = vfsStream::setup();

        $subject = new CreateDatabase();
        $subject->setLog(new NullLogger());
        $subject->setIgnoredTables('/^cf_/');
        $subject->enableAnonymizer();
        $subject->setDirectory($dir->url() . '/');
        $subject->generate();
    }
}
