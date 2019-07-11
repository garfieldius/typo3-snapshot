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
        $initStatements = [
            'SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT;',
            'SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS;',
            'SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION;',
            'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;',
            'SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;',
            'SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;',
        ];

        $finishStatements = [
            'SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;',
            'SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;',
            'SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT;',
            'SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS;',
            'SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION;',
        ];

        GeneralUtility::mkdir_deep($this->directory);

        // Create a separate dump for every connection
        foreach (array_keys($GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']) as $connectionName) {
            $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionByName($connectionName);
            $connection->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');

            $targetFile = $this->directory . $connectionName . '.sql';
            $fh = fopen($targetFile, 'wb');
            fwrite($fh, implode("\n", $initStatements) . "\n\n");

            foreach ($connection->getSchemaManager()->listTables() as $table) {
                // Add a drop statement at first
                fwrite($fh, "DROP TABLE IF EXISTS `{$table->getName()}`;\n");

                // Add schema DDL without any collation or charset information
                $data = $connection->query('SHOW CREATE TABLE `' . $table->getName() . '`')->fetchAll();
                $sql = end($data[0]);
                $sql = preg_replace('/\\s*(DEFAULT )?(COLLATE|CHARSET)( |=)[a-z0-9_]+/', '', $sql);
                fwrite($fh, $sql . ";\n\n");

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

                    $fields = [];
                    $values = [];

                    foreach ($record as $field => $value) {
                        $fields[] = '`' . $field . '`';
                        $values[] = $this->encode($connection, $value);
                    }

                    $fields = implode(', ', $fields);
                    $values = implode(', ', $values);

                    // Create one insert per record
                    $sql = sprintf('INSERT INTO `%s` (%s) VALUES (%s);', $table->getName(), $fields, $values);
                    fwrite($fh, $sql . "\n");
                }

                fwrite($fh, "\n");
            }

            fwrite($fh, implode("\n", $finishStatements) . "\n\n");
            fclose($fh);

            GeneralUtility::makeInstance(Process::class, ['gzip', '-9', $targetFile])->run();
        }
    }

    private function encode(Connection $cnx, $value): string
    {
        if (is_null($value)) {
            return 'NULL';
        }

        $value = (string) $value;

        if (preg_match('/^-?[0-9]+(:?\\.[0-9]+)?$/', $value)) {
            return $value;
        }

        return $cnx->quote($value);
    }
}
