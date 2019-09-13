<?php
declare(strict_types=1);

/*
 * (c) 2019 Georg Großberger <contact@grossberger-ge.org>
 *
 * This file is free software; you can redistribute it and/or
 * modify it under the terms of the Apache License 2.0
 *
 * For the full copyright and license information see
 * <https://www.apache.org/licenses/LICENSE-2.0>
 */

$EM_CONF[$_EXTKEY] = [
    'title'            => 'Snapshot',
    'description'      => 'Create and restore DB and fileadmin snapshots',
    'version'          => '1.2.0',
    'state'            => 'stable',
    'category'         => 'misc',
    'clearCacheOnLoad' => 0,
    'constraints'      => [
        'depends' => [
            'typo3' => '9.5.0-10.4.999'
        ],
        'conflicts' => [],
        'suggests'  => [],
    ],
];
