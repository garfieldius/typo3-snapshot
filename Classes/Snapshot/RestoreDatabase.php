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
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\StringUtility;

/**
 * RestoreDatabase
 *
 * @author Georg Großberger <contact@grossberger-ge.org>
 */
class RestoreDatabase
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

    public function restore()
    {
        $files = GeneralUtility::getFilesInDir($this->directory, 'sql,sql.gz', true);
        $pool = GeneralUtility::makeInstance(ConnectionPool::class);

        foreach ($files as $file) {
            $connectionName = preg_replace('/\\.sql(\\.gz)?$/', '', basename($file));

            $uncompressed = false;
            if (StringUtility::endsWith($file, '.gz')) {
                $cmd = [
                    'gzip',
                    '-d',
                    '-k',
                    $file,
                ];

                GeneralUtility::makeInstance(Process::class, $cmd)->mustRun();
                $file = substr($file, 0, -3);
                $uncompressed = true;
            }

            $this->logger->info(sprintf('Importing file %s into connection %s', $file, $connectionName));
            $connection = $pool->getConnectionByName($connectionName);
            $fh = fopen($file, 'r');
            $buffer = '';

            while (!feof($fh)) {
                $line = fgets($fh, 4096);
                $buffer .= $line;

                if (StringUtility::endsWith(rtrim($line), ';')) {
                    $this->logger->debug($buffer);
                    $connection->exec($buffer);
                }
            }

            if (trim($buffer)) {
                $connection->exec($buffer);
            }

            fclose($fh);

            if ($uncompressed) {
                @unlink($file);
            }
        }
    }
}
