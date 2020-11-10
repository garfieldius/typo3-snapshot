<?php
declare(strict_types=1);
namespace GrossbergerGeorg\Snapshot\Tests\Anonymize;

/*
 * (c) 2020 Georg Großberger <contact@grossberger-ge.org>
 *
 * This file is free software; you can redistribute it and/or
 * modify it under the terms of the Apache License 2.0
 *
 * For the full copyright and license information see
 * <https://www.apache.org/licenses/LICENSE-2.0>
 */

use GrossbergerGeorg\Snapshot\Anonymize\Anonymizer;
use PHPUnit\Framework\TestCase;

/**
 * Check the anonymizer by replacing a fixed value array
 * with random data
 *
 * @author Georg Großberger <contact@grossberger-ge.org>
 */
class AnonymizerTest extends TestCase
{
    const CHANGED_TOKEN = 'some private value';

    /**
     * @dataProvider anonymizeDataProvider
     * @param $table
     * @param $record
     * @param $unchanged
     * @param $changed
     */
    public function testAnonymizeRecord($table, $record, $unchanged, $changed)
    {
        $subject = new Anonymizer();
        $subject->clearRecord($table, $record);

        foreach ($unchanged as $key => $value) {
            static::assertSame($value, $record[$key]);
        }

        foreach ($changed as $key => $value) {
            static::assertNotSame($value, $record[$key]);
        }
    }

    public function testUnknownSettingThrowsException()
    {
        $this->expectException(\UnexpectedValueException::class);

        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['snapshot']['anonymizeSettings'] = [
            'fe_users' => [
                'twitter' => 'twitterHandle',
            ],
        ];
        $table = 'fe_users';
        $record = [
            'twitter' => '@user',
        ];

        $subject = new Anonymizer();
        $subject->clearRecord($table, $record);
    }

    public function anonymizeDataProvider()
    {
        $datasets = [];
        $data = [
            'sys_log' => [
                'IP',
            ],
            'be_users' => [
                'email',
                'realName',
            ],
            'fe_users' => [
                'name',
                'first_name',
                'middle_name',
                'last_name',
                'address',
                'telephone',
                'fax',
                'email',
                'title',
                'zip',
                'city',
                'country',
                'www',
                'company',
            ],
            'tt_address' => [
                'name',
                'first_name',
                'middle_name',
                'last_name',
                'birthday',
                'email',
                'phone',
                'mobile',
                'fax',
                'www',
                'address',
                'building',
                'room',
                'company',
                'position',
                'city',
                'zip',
                'region',
                'country',
                'skype',
                'twitter',
                'facebook',
                'linkedin',
                'latitude',
                'longitude',
            ],
        ];

        foreach ($data as $table => $fields) {
            $unchanged = [
                'uid' => 1,
                'pid' => 2,
            ];

            $changed = [];

            foreach ($fields as $field) {
                $changed[$field] = static::CHANGED_TOKEN;
            }

            $datasets[] = [
                $table,
                $unchanged + $changed,
                $unchanged,
                $changed
            ];
        }

        return $datasets;
    }
}
