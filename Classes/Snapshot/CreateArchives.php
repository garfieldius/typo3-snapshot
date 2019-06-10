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

use FPDF;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Type\File\ImageInfo;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Create archives of files of Storages of type "Local"
 *
 * @author Georg Großberger <contact@grossberger-ge.org>
 */
class CreateArchives
{
    private $directory = '';

    private $small = false;

    /**
     * @var LoggerInterface
     */
    private $log;

    /**
     * @param string $directory
     */
    public function setDirectory(string $directory): void
    {
        $this->directory = $directory;
    }

    /**
     * @param bool $small
     */
    public function setSmall(bool $small): void
    {
        $this->small = $small;
    }

    /**
     * @param LoggerInterface $log
     */
    public function setLog(LoggerInterface $log): void
    {
        $this->log = $log;
    }

    public function generate()
    {
        $storages = GeneralUtility::makeInstance(StorageRepository::class)->findByStorageType('Local');

        /** @var ResourceStorage $storage */
        foreach ($storages as $storage) {
            $targetFile =
                $this->directory .
                $storage->getUid() .
                '--' .
                trim(preg_replace('/[^a-z0-9A-Z]+/', '-', strtolower($storage->getName())), '-') .
                '.tar.gz';

            $processedFolder = $storage->getProcessingFolder()->getName();
            $sourcePath = $storage->getConfiguration()['basePath'];

            // Relative basePaths are relative to the public directory
            if ($storage->getConfiguration()['pathType'] == 'relative') {
                $sourcePath = Environment::getPublicPath() . DIRECTORY_SEPARATOR . $sourcePath;
            }

            $sourcePath = rtrim($sourcePath, '/\\') . DIRECTORY_SEPARATOR;

            if ($this->small) {
                $sourcePath = $this->createReducedFiles($storage, $sourcePath);
            }

            $cmd = [
                'tar',
                '-czf',
                $targetFile,
                '--exclude',
                '*/' . $processedFolder . '/*',
                '-C',
                $sourcePath,
            ];

            GeneralUtility::makeInstance(Process::class, $cmd)->mustRun();

            if ($this->small) {
                GeneralUtility::rmdir($sourcePath, true);
            }
        }
    }

    private function createReducedFiles(ResourceStorage $storage, string $sourcePath): string
    {
        $path = Environment::getVarPath() . DIRECTORY_SEPARATOR .
            'snapshot-' . $GLOBALS['EXEC_TIME'] . DIRECTORY_SEPARATOR .
            'storage-' . $storage->getUid() . DIRECTORY_SEPARATOR;

        GeneralUtility::mkdir_deep($path);

        $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_file');
        $qb->select('t0.identifier');
        $qb->from('sys_file', 't0');
        $qb->innerJoin('t0', 'sys_file_reference', 't1', 't0.uid=t1.uid_local');
        $qb->where(
            $qb->expr()->eq('t0.storage', $storage->getUid()),
            $qb->expr()->eq('t0.missing', 0)
        );

        foreach ($qb->execute() as $file) {
            $filename = ltrim($file['identifier'], '/');
            $sourceFile = $sourcePath . $filename;
            $targetFile = $path . $filename;
            GeneralUtility::mkdir_deep(dirname($targetFile));

            if (filesize($sourceFile) < 102400) {
                copy($sourceFile, $targetFile);
            } else {
                $this->writeStub($targetFile, $sourceFile);
            }

            GeneralUtility::fixPermissions($targetFile);
        }

        return $path;
    }

    private function writeStub(string $target, string $source)
    {
        switch (strtolower(pathinfo($target, PATHINFO_EXTENSION))) {
            // Write a dummy image with the filename
            case 'jpg':
            case 'jpeg':
            case 'png':
            case 'gif':
            case 'webp':
                $info = GeneralUtility::makeInstance(ImageInfo::class, $source);
                $this->writeImageStub(
                    $info->getWidth(),
                    $info->getHeight(),
                    $target
                );
                break;

            // Write a dummy pdf containing the filename
            case 'pdf':
                $pdf = new FPDF();
                $pdf->AddPage();
                $pdf->SetFont('Arial', '', 12);
                $pdf->Cell(0, 10, basename($target));
                $pdf->Output('F', $target);
                break;

            // For others, write a file with the content of the checksum of the original file
            default:
                $content = hash_file('md5', $source);
                file_put_contents($target, $content);
        }
    }

    private function writeImageStub(int $width, int $height, string $target)
    {
        $img = imagecreatetruecolor($width, $height);
        $bg = imagecolorallocate($img, 255, 255, 255);
        imagefill($img, 0, 0, $bg);

        $fg = imagecolorallocate($img, 128, 128, 128);
        imagestring($img, 1, 5, 5, basename($target), $fg);

        switch (strtolower(pathinfo($target, PATHINFO_EXTENSION))) {
            case 'gif':
                imagetruecolortopalette($img, false, 255);
                imagegif($img, $target);
                break;

            case 'jpg':
            case 'jpeg':
                imagejpeg($img, $target);
                break;

            case 'png':
                imagepng($img, $target);
                break;

            case 'webp':
                imagewebp($img, $target);
        }

        imagedestroy($img);
    }
}
