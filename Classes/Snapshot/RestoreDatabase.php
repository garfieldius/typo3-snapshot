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
 * Restore database snapshots by importing all dumps in snapshot directory
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
        $mysql = '';

        foreach (explode(PATH_SEPARATOR, (string) getenv('PATH')) as $path) {
            $bin = $path . DIRECTORY_SEPARATOR . 'mysql';

            if (PHP_OS_FAMILY == 'Windows') {
                $bin .= '.exe';
            }

            $this->logger->debug('Looking for mysql binary ' . $bin);

            if (is_file($bin) && is_executable($bin)) {
                $mysql = $bin;
            }
        }

        foreach ($files as $file) {
            $connectionName = preg_replace('/\\.sql(\\.gz)?$/', '', basename($file));

            if (empty($GLOBALS['TYPO3_CONF_VARS']['DB']['Connections'][$connectionName])) {
                $this->logger->error('No connection configured with name ' . $connectionName);
                continue;
            }

            $uncompressed = false;

            if (StringUtility::endsWith($file, '.gz')) {
                $cmd = [
                    'gzip',
                    '-d',
                    $file,
                ];

                GeneralUtility::makeInstance(Process::class, $cmd)->mustRun();
                $file = substr($file, 0, -3);
                $uncompressed = true;
            }

            $this->logger->info(sprintf('Importing file %s into connection %s', $file, $connectionName));
            $connection = $pool->getConnectionByName($connectionName);

            if ($mysql && $connection->getDatabasePlatform()->getName() == 'mysql') {
                $this->logger->info('Using mysql program for import');
                $config = $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections'][$connectionName];
                $program = $mysql .
                    ' -u' . escapeshellarg($config['user']) .
                    ' -p' . escapeshellarg($config['password']) .
                    ' -h' . escapeshellarg($config['host']) .
                    ' ' . escapeshellarg($config['dbname']) .
                    ' < ' . escapeshellarg($file);
                Process::fromShellCommandline($program)->mustRun();
            } else {
                $this->logger->info('Using PHP based import');
                $fh = fopen($file, 'r');
                $buffer = '';

                while (!feof($fh)) {
                    $line = fgets($fh, 4096);

                    if (!is_string($line)) {
                        break;
                    }

                    $buffer .= $line;

                    if (StringUtility::endsWith(rtrim($line), ';')) {
                        $this->logger->debug($buffer);
                        $connection->exec($buffer);
                        $buffer = '';
                    }
                }

                fclose($fh);

                if (trim($buffer)) {
                    $connection->exec($buffer);
                }
            }

            // If it was decompressed, compress it again
            if ($uncompressed) {
                $cmd = [
                    'gzip',
                    '-9',
                    $file,
                ];

                GeneralUtility::makeInstance(Process::class, $cmd)->run();
            }
        }
    }
}
