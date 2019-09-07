<?php
declare(strict_types=1);
namespace GrossbergerGeorg\Snapshot\Snapshot;

/*
 * (c) 2019 Georg Großberger <contact@grossberger-ge.org>
 *
 * This file is free software; you can redistribute it and/or
 * modify it under the terms of the Apache License 2.0
 *
 * For the full copyright and license information see
 * <https://www.apache.org/licenses/LICENSE-2.0>
 */

use GrossbergerGeorg\Snapshot\Anonymize\Anonymizer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Create a database snapshot for each configured database
 *
 * @author Georg Großberger <contact@grossberger-ge.org>
 */
class CreateDatabase
{
    private $directory = '';

    /**
     * @var LoggerInterface
     */
    private $log;

    private $ignoredTables = '/^cf_|^cache_|sys_history|sys_log|sys_file_processed|sys_lockedrecords|tx_solr_.+/i';

    /**
     * @var Anonymizer
     */
    private $anomizer;

    /**
     * @param string $directory
     */
    public function setDirectory(string $directory): void
    {
        $this->directory = $directory;
    }

    /**
     * @param LoggerInterface $log
     */
    public function setLog(LoggerInterface $log): void
    {
        $this->log = $log;
    }

    /**
     * @param string $ignoredTables
     */
    public function setIgnoredTables(string $ignoredTables): void
    {
        $this->ignoredTables = $ignoredTables;
    }

    public function enableAnonymizer()
    {
        $this->anomizer = GeneralUtility::makeInstance(Anonymizer::class);
    }

    public function generate()
    {
        GeneralUtility::mkdir_deep($this->directory);

        // Create a separate dump for every connection
        foreach (array_keys($GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']) as $connectionName) {
            $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionByName($connectionName);
            $isMysql = $connection->getDatabasePlatform()->getName() === 'mysql';

            if ($isMysql) {
                $connection->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
            }

            $targetFile = $this->directory . $connectionName . '.sql';
            $file = GeneralUtility::makeInstance(SQLFile::class, $targetFile, $connection, $isMysql);

            foreach ($connection->getSchemaManager()->listTables() as $table) {
                // Add a drop statement at first
                $file->addDropTable($table->getName());

                // Add schema DDL without any collation or charset information
                foreach ($connection->getDatabasePlatform()->getCreateTableSQL($table) as $sql) {
                    $file->addCreateTable($sql);
                }

                // If the table is ignored, skip adding the data
                if (preg_match($this->ignoredTables, $table->getName())) {
                    continue;
                }

                $queryBuilder = $connection->createQueryBuilder();
                $queryBuilder->select('*');
                $queryBuilder->from($table->getName());
                $queryBuilder->getRestrictions()->removeAll();
                $queryBuilder->getRestrictions()->add(GeneralUtility::makeInstance(DeletedRestriction::class));

                // Iterate over all records and add them one by one
                foreach ($queryBuilder->execute() as $record) {
                    // Anonymize record if requested
                    if ($this->anomizer) {
                        $this->anomizer->clearRecord($table->getName(), $record);
                    }

                    $file->addInsert($table->getName(), $record);
                }
            }

            $file->close();

            GeneralUtility::makeInstance(Process::class, ['gzip', '-9', $targetFile])->run();
        }
    }
}
