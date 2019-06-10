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

return [
    'snapshot:create' => [
        'class' => \GrossbergerGeorg\Snapshot\Command\SnapshotCreate::class,
    ],
    'snapshot:restore' => [
        'class'       => \GrossbergerGeorg\Snapshot\Command\SnapshotRestore::class,
        'schedulable' => false,
    ],
];
