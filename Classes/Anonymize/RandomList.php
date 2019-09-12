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

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Load a list of static values and provide a random value
 * from it
 *
 * @author Georg Großberger <contact@grossberger-ge.org>
 */
class RandomList
{
    const CITY = 'cities';
    const COUNTRY = 'countries';
    const COMPANY = 'companies';
    const HOST = 'hosts';
    const NAME = 'names_first';
    const FAMILY = 'names_main';

    private $values = [];

    private $max = 0;

    private static $lists = [];

    public function __construct(string $list)
    {
        $loaded = require dirname(dirname(__DIR__)) . '/Resources/Private/PHP/' .$list . '.php';
        $this->values = array_values($loaded);
        $this->max = count($this->values) - 1;
    }

    /**
     * Get or create a RandomList object with the given list
     *
     * @param string $list
     * @return RandomList
     */
    public static function of(string $list): RandomList
    {
        if (!isset(static::$lists[$list])) {
            static::$lists[$list] = GeneralUtility::makeInstance(static::class, $list);
        }

        return static::$lists[$list];
    }

    /**
     * Get a random value of the loaded list
     *
     * @return string
     */
    public function get(): string
    {
        return $this->values[random_int(0, $this->max)];
    }
}
