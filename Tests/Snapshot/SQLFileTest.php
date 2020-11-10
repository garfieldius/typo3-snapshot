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

use GrossbergerGeorg\Snapshot\Snapshot\SQLFile;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Database\Connection;

/**
 * Basic test of SQLFile abstraction
 *
 * @author Georg Großberger <g.grossberger@supseven.at>
 */
class SQLFileTest extends TestCase
{
    public function testSqlWrite()
    {
        $cnx = $this->getMockBuilder(Connection::class)->disableOriginalConstructor()->getMock();
        $cnx->expects(static::any())->method('quote')->willReturnCallback(function ($value) {
            return "'${value}'";
        });
        $fs = vfsStream::create([
            'generic.sql' => '',
            'mysql.sql'   => '',
        ]);

        $table = 'tx_snapshot';
        $create = 'CREATE TABLE tx_snapshot_test (uid int(11))';

        $expected =
            "DROP TABLE IF EXISTS ${table};\n" .
            "${create};\n";

        $file = $fs->getChild('generic.sql')->url();

        $subject = new SQLFile($file, false);
        $subject->addDropTable($table);
        $subject->addCreateTable($create);
        $subject->close();

        static::assertStringEqualsFile($file, $expected);

        $expected =
            "DROP TABLE IF EXISTS ${table};\n" .
            "${create};\n";

        $file = $fs->getChild('mysql.sql')->url();

        $subject = new SQLFile($file, false);
        $subject->addDropTable($table);
        $subject->addCreateTable($create);
        $subject->close();

        static::assertStringContainsString(file_get_contents($file), $expected);
    }
}
