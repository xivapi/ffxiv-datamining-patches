<?php

/**
 * This script zips up all the extracts
 */

require __DIR__.'/vendor/autoload.php';

$console = new \Symfony\Component\Console\Output\ConsoleOutput();
$console = $console->section();

// folder to zip up
$rootpath = __DIR__.'/extracts';

// Initialize archive object
$zip = new \ZipArchive();
$zip->open(__DIR__.'/extracts.zip', \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

// Create recursive directory iterator
/** @var SplFileInfo[] $files */
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($rootpath),
    RecursiveIteratorIterator::LEAVES_ONLY
);

$count = 0;
$total = iterator_count($files);
$console->overwrite("Total Files: ". number_format($total));

foreach ($files as $name => $file)
{
    $count++;
    $percent = round(($count / $total) * 100);
    if ($count % 200 == 0) {
        $console->overwrite("Progress: {$percent}% - {$count} / {$total}");
    }
    
    // Skip directories (they would be added automatically)
    if (!$file->isDir())
    {
        // Get real and relative path for current file
        $filePath = $file->getRealPath();
        $relativePath = substr($filePath, strlen($rootpath) + 1);
        
        // Add current file to archive
        $zip->addFile($filePath, $relativePath);
    }
}

// write to file
$console->writeln("Saving zip file, this will take about 5 minutes.");

// Zip archive will be created only after closing object
$zip->close();
