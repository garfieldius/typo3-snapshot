<?php
declare(strict_types=1);
namespace GrossbergerGeorg\Snapshot\Anonymize;

/*
 * (c) 2019 Georg Großberger <contact@grossberger-ge.org>
 *
 * This file is free software; you can redistribute it and/or
 * modify it under the terms of the Apache License 2.0
 *
 * For the full copyright and license information see
 * <https://www.apache.org/licenses/LICENSE-2.0>
 */

use TYPO3\CMS\Core\Utility\ArrayUtility;

/**
 * Helper for anonymizing data from a database
 *
 * @author Georg Großberger <contact@grossberger-ge.org>
 */
class Anonymizer
{
    private $configuration = [];

    public function __construct()
    {
        $configuration = [
            'sys_log' => [
                'IP' => 'ip',
            ],
            'be_users' => [
                'email'    => 'email',
                'realName' => 'name',
            ],
            'fe_users' => [
                'name'        => 'name',
                'first_name'  => 'given',
                'middle_name' => 'empty',
                'last_name'   => 'family',
                'address'     => 'street',
                'telephone'   => 'phone',
                'fax'         => 'phone',
                'email'       => 'email',
                'title'       => 'empty',
                'zip'         => 'postcode',
                'city'        => 'city',
                'country'     => 'countryName',
                'www'         => 'website',
                'company'     => 'company',
            ],
            'tt_address' => [
                // Usually not necessary, uncomment if needed
                // 'gender' => 'empty',
                // 'title' => 'empty',

                'name'        => 'name',
                'first_name'  => 'given',
                'middle_name' => 'empty',
                'last_name'   => 'family',
                'birthday'    => 'timestamp',
                'email'       => 'email',
                'phone'       => 'phone',
                'mobile'      => 'phone',
                'fax'         => 'phone',
                'www'         => 'website',
                'address'     => 'addressShort',
                'building'    => 'addressNumber',
                'room'        => 'addressNumber',
                'company'     => 'company',
                'position'    => 'empty',
                'city'        => 'city',
                'zip'         => 'postcode',
                'region'      => 'empty',
                'country'     => 'countryName',
                'skype'       => 'handle',
                'twitter'     => 'handle',
                'facebook'    => 'handle',
                'linkedin'    => 'handle',
                'latitude'    => 'latitude',
                'longitude'   => 'longitude',
            ],
        ];

        if (!empty($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['snapshot']['anonymizeSettings'])) {
            ArrayUtility::mergeRecursiveWithOverrule(
                $configuration,
                $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['snapshot']['anonymizeSettings']
            );
        }

        $this->configuration = $configuration;
    }

    /**
     * Anonymize the given record of specified database
     *
     * @param string $table
     * @param array $record
     */
    public function clearRecord(string $table, array &$record)
    {
        if (!empty($this->configuration[$table])) {
            $config = $this->configuration[$table];

            foreach (array_keys($record) as $field) {
                if (!isset($config[$field]) || trim($record[$field]) === '') {
                    continue;
                }

                switch ($config[$field]) {
                    case 'given':
                        $record[$field] = $this->randomGivenName();
                        break;

                    case 'family':
                        $record[$field] = $this->randomFamilyName();
                        break;

                    case 'name':
                        $record[$field] = $this->randomName();
                        break;

                    case 'empty':
                        $record[$field] = '';
                        break;

                    case 'ip':
                        $record[$field] = '127.0.' . random_int(0, 255) . '.' . random_int(0, 255);
                        break;

                    case 'street':
                        $record[$field] = $this->randomStreetName();
                        break;

                    case 'addressShort':
                        $record[$field] = $this->randomStreetName(true);
                        break;

                    case 'addressNumber':
                        $record[$field] = $this->randomAddressNumber();
                        break;

                    case 'postcode':
                        $record[$field] = $this->randomPostalCode();
                        break;

                    case 'email':
                        $record[$field] = $this->randomEmailAddress();
                        break;

                    case 'website':
                        $record[$field] = $this->randomWebsite();
                        break;

                    case 'phone':
                        $record[$field] = $this->randomPhoneNumber();
                        break;

                    case 'city':
                        $record[$field] = $this->randomCity();
                        break;

                    case 'countryName':
                        $record[$field] = $this->randomCountryName();
                        break;

                    case 'company':
                        $record[$field] = $this->randomCompany();
                        break;

                    case 'timestamp':
                        $record[$field] = $this->randomTimestamp();
                        break;

                    case 'handle':
                        $record[$field] = $this->randomSocial();
                        break;

                    case 'latitude':
                        $record[$field] = (string) (random_int(-9000000, 9000000) / 100000);
                        break;

                    case 'longitude':
                        $record[$field] = (string) (random_int(-18000000, 18000000) / 100000);
                        break;

                    default:
                        throw new \UnexpectedValueException('No anonymizing strategy named ' . $config[$field]);
                }
            }
        }
    }

    private function randomAddressNumber(): string
    {
        return (string) random_int(1, 999);
    }

    private function randomGivenName(): string
    {
        return ucwords(RandomList::of(RandomList::NAME)->get());
    }

    private function randomFamilyName(): string
    {
        return ucwords(RandomList::of(RandomList::FAMILY)->get());
    }

    private function randomName(): string
    {
        return $this->randomGivenName() . ' ' . $this->randomFamilyName();
    }

    private function randomStreetName(bool $short = false): string
    {
        $street = $this->randomFamilyName() . (random_int(0, 100) > 50 ? 'street' : 'road');

        if (!$short) {
            $street .= ' ' . $this->randomAddressNumber();
        }

        return $street;
    }

    private function randomPostalCode(): string
    {
        return (string) random_int(1000, 99999);
    }

    private function randomEmailAddress(): string
    {
        return
            strtolower($this->randomGivenName()) .
            '.' .
            strtolower($this->randomFamilyName()) .
            '@' .
            $this->randomHost();
    }

    private function randomWebsite(): string
    {
        return 'https://www.' . $this->randomHost() . '/';
    }

    private function randomHost(): string
    {
        return RandomList::of(RandomList::HOST)->get();
    }

    private function randomPhoneNumber(): string
    {
        return
            '+' . random_int(20, 60) .
            ' / (0)' . random_int(200, 990) .
            ' ' . random_int(10, 99) .
            ' ' . random_int(10, 99) .
            ' ' . random_int(10, 999);
    }

    private function randomCountryName()
    {
        return RandomList::of(RandomList::COUNTRY)->get();
    }

    private function randomCity()
    {
        return RandomList::of(RandomList::CITY)->get();
    }

    private function randomCompany()
    {
        return RandomList::of(RandomList::COMPANY)->get();
    }

    private function randomTimestamp(): string
    {
        $year = 3600 * 365;
        $max = time() - $year * 2;
        $min = $max - $year * 80;

        return (string) random_int($min, $max);
    }

    private function randomSocial()
    {
        return '@' . strtolower(str_replace(' ', '.', $this->randomName()));
    }
}
