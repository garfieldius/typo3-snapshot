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

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Core\Utility\StringUtility;

/**
 * Helper for SQL file generations
 *
 * @author Georg Großberger <contact@grossberger-ge.org>
 */
class SQLFile
{
    /**
     * @var resource
     */
    private $fileHandle;

    private $initStatements = [
        'SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT;',
        'SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS;',
        'SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION;',
        'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;',
        'SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;',
        'SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;',
    ];

    private $finishStatements = [
        'SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;',
        'SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;',
        'SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT;',
        'SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS;',
        'SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION;',
    ];

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var bool
     */
    private $useInitStatements;

    /**
     * SQLFile constructor.
     * @param string $file
     * @param Connection $connection
     * @param bool $useInitStatements
     */
    public function __construct(string $file, Connection $connection, bool $useInitStatements)
    {
        $this->fileHandle = fopen($file, 'wb+');
        $this->connection = $connection;
        $this->useInitStatements = $useInitStatements;

        if ($this->useInitStatements) {
            $this->write(...$this->initStatements);
        }
    }

    /**
     * Add a drop table statement
     *
     * @param string $table
     */
    public function addDropTable(string $table): void
    {
        $this->write("DROP TABLE IF EXISTS {$table};");
    }

    /**
     * Add a SQL statement, treat it as a create table DDL
     * @param string $sql
     */
    public function addCreateTable(string $sql): void
    {
        $sql = preg_replace('/\\s*(DEFAULT )?(COLLATE|CHARSET)( |=)[a-z0-9_]+/', '', trim($sql));
        $this->write(rtrim($sql, "\n\r\t\0 ;") . ';');
    }

    /**
     * Create an insert statement for given record and table
     *
     * @param string $table
     * @param array $record
     */
    public function addInsert(string $table, array $record): void
    {
        $fields = [];
        $values = [];

        foreach ($record as $field => $value) {
            $fields[] = $field;
            $values[] = $this->encode($value);
        }

        $fields = implode(', ', $fields);
        $values = implode(', ', $values);

        // Create one insert per record
        $sql = sprintf('INSERT INTO %s (%s) VALUES (%s);', $table, $fields, $values);
        $this->write($sql);
    }

    /**
     * Close file pointer
     */
    public function close(): void
    {
        if ($this->useInitStatements) {
            $this->write(...$this->finishStatements);
        }

        fclose($this->fileHandle);
    }

    /**
     * Write given string to file
     *
     * @param string ...$sqls
     */
    private function write(string ...$sqls): void
    {
        foreach ($sqls as $sql) {
            if (trim($sql) !== '' && !StringUtility::endsWith(rtrim($sql), ';')) {
                $sql .= ';';
            }

            fwrite($this->fileHandle, $sql . "\n");
        }
    }

    /**
     * Get a SQL value from given PHP value
     *
     * @param $value
     * @return string
     */
    private function encode($value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        $value = (string) $value;

        // Only write simple numbers as literals
        if (MathUtility::canBeInterpretedAsInteger($value) || MathUtility::canBeInterpretedAsFloat($value)) {
            return $value;
        }

        return $this->connection->quote($value);
    }
}
