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

use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorageInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Restore file snapshots by unpacking the archives into the storages base directory
 *
 * @author Georg Großberger <contact@grossberger-ge.org>
 */
class RestoreFiles
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var string
     */
    private $directory = '';

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * @param string $directory
     */
    public function setDirectory(string $directory): void
    {
        $this->directory = $directory;
    }

    /**
     * Run restore files for set directory
     */
    public function restore(): void
    {
        // Find archives in snapshot
        $files = GeneralUtility::getFilesInDir($this->directory, 'tar,tar.gz', true);
        $resourceFactory = ResourceFactory::getInstance();

        foreach ($files as $file) {
            // Get UID of storage
            $filename = basename($file);
            $storageId = (int) substr($filename, 0, strpos($filename, '--'));

            if ($storageId < 1) {
                $this->logger->error(sprintf('Invalid storage name %s', $filename));
                continue;
            }

            // Get storage of given UID
            $storage = $resourceFactory->getStorageObject($storageId);

            if (!$storage instanceof ResourceStorageInterface || $storage->getDriverType() !== 'Local') {
                $this->logger->error('No local driver storage "' . $filename . '" with UID ' . $storageId);
                continue;
            }

            // Extract into root path of the storage
            $storagePath = $storage->getConfiguration()['basePath'];

            if ($storage->getConfiguration()['pathType'] === 'relative') {
                $storagePath = Environment::getPublicPath() . DIRECTORY_SEPARATOR . $storagePath;
            }

            $cmd = [
                'tar',
                '-xf',
                $file,
                '-C',
                $storagePath,
            ];

            GeneralUtility::makeInstance(Process::class, $cmd)->mustRun();
        }
    }
}
