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

use Doctrine\DBAL\Platforms\MySQL57Platform;
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
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DefaultRestrictionContainer;
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
        $dataTable->addColumn('uid', 'integer');
        $dataTable->addColumn('title', 'text');
        $cacheTable = new Table('cf_pages');
        $cacheTable->addColumn('key', 'text', ['length' => 255]);
        $cacheTable->addColumn('data', 'text');

        $schemaManager = $this->makeMock(MySqlSchemaManager::class);
        $schemaManager->expects(static::once())->method('listTables')->willReturn([$dataTable, $cacheTable]);

        $dataRecords = [
            [
                'uid'    => 1,
                'pid'    => 2,
                'header' => 'Content Element',
                'bodyt'  => null,
            ]
        ];

        $dataRecordsResult = $this->makeMock(Statement::class);
        $dataRecordsResult->expects(static::any())->method('getIterator')->willReturn(new \ArrayObject($dataRecords));

        $queryBuilder = $this->makeMock(QueryBuilder::class);
        $queryBuilder->expects(static::any())->method('getRestrictions')->willReturn(new DefaultRestrictionContainer());
        $queryBuilder->expects(static::any())->method('execute')->willReturn($dataRecordsResult);

        $platform = new MySQL57Platform();

        $cnx = $this->makeMock(Connection::class);
        $cnx->expects(static::any())->method('getSchemaManager')->willReturn($schemaManager);
        $cnx->expects(static::any())->method('createQueryBuilder')->willReturn($queryBuilder);
        $cnx->expects(static::any())->method('getDatabasePlatform')->willReturn($platform);
        $cnx->expects(static::any())->method('quote')->willReturnCallback(function ($value) {
            return "'${value}'";
        });

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
