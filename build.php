<?php

/**
 * This works by going from the latest patch extract and assigning all
 * content the "latest" patch, it will then look for those files in previous
 * patches and if they are found, it will attempt to remove any "placeholder"
 * content and assign that patch, it will keep going down until the file is not found
 * and then that file is complete.
 *
 * Files that have since deleted are ignored. I don't care about them
 */

require_once __DIR__ . '/vendor/autoload.php';

// reset
write("Resetting");
sleep(1);
unlink(__DIR__.'/log.txt');
array_map('unlink', glob(__DIR__.'/patchdata/*.*'));
sleep(2);

use League\Csv\Reader;
use League\Csv\Statement;

$forceContentName = $argv[1] ?? false;

function write($text)
{
    $date = date('Y-m-d H:i:s');
    $text = "[{$date}] {$text} \n";
    file_put_contents(__DIR__.'/log.txt', $text, FILE_APPEND);
    echo $text;
}

function getFileList($folder)
{
    $files = [];
    foreach (scandir($folder) as $file) {
        $pi = pathinfo($file);

        if (isset($pi['extension']) && $pi['extension'] == 'csv') {
            $files[] = $folder . $file;
        }
    }

    asort($files);
    return $files;
}

function getCsvData($filename)
{
    $csv = Reader::createFromPath($filename);

    // parse column types
    $stmt = (new Statement())->offset(2)->limit(1);
    $typeColumns = $stmt->process($csv)->fetchOne();

    // find "str" columns
    $stringColumns = [];
    foreach ($typeColumns as $i => $type) {
        if ($type == 'str') {
            $stringColumns[] = $i;
        }

        // limit this to 5 because some files (eg Quests) have a fuck ton of str data
        if (count($stringColumns) == 5) {
            break;
        }
    }

    $csvRecords = (new Statement())->offset(3)->process($csv)->getRecords();

    return [
        $csvRecords,
        $stringColumns
    ];
}

function saveData($contentName, $data)
{
    file_put_contents(
        __DIR__.'/patchdata/'. $contentName .'.json',
        json_encode($data, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK)
    );
}

function isPlaceholder($record, $stringColumns)
{
    // grab all string values
    $strValues = [];
    foreach ($stringColumns as $index) {
        $strValues[$index] = trim($record[$index]);
    }

    // grab the total non empty str count
    $strValuesNonEmpty = count(array_filter($strValues));

    // work out the percentage of empty str values, the reason we do this is because
    // sometimes a placeholder will be added with various in-game codes or file names
    // but missing key names and thus should be considered as not added
    $percentage = $strValuesNonEmpty == 0 ? 0 : ($strValuesNonEmpty / count($strValues)) * 100;

    return $percentage > 30;
}

function reportResults($PatchData, $patchName, $totalRecords, $skippedArray, $skippedRecords, $contentName)
{
    global $forceContentName;

    $totalSkipped = $skippedRecords == 0 ? 0 : round(($skippedRecords / $totalRecords * 100), 1);

    write("[{$patchName}] ". count($PatchData) ."/{$totalRecords} Assigned {$skippedRecords}/{$totalRecords} ({$totalSkipped}%) Placeholders");

    if ($totalSkipped > 30) {
        write("[{$patchName}] !!! Over 30% of rows were skipped, check the IDs in the CSV just to be sure");
        write("[{$patchName}] !!! You can run this manually via: php build.php {$contentName}");

        if ($forceContentName) {
            write('[{$patchName}] !!! Skipped: '. implode(',', $skippedArray));
        }
    }

    write("------------------------------------------------------------");
}

function handleFile($filename, $patchId, $patchName)
{
    global $ContentList, $forceContentName;

    $pathInfo    = pathinfo($filename);
    $contentName = $pathInfo['filename'];
    $ContentList[$contentName] = true;

    // skip if not forced
    if ($forceContentName && $forceContentName != $contentName) {
        return;
    }

    write("[{$patchName}] CSV {$contentName}");

    [$records, $stringColumns] = getCsvData($filename);

    $PatchData = [];

    // load existing patch data id it is there
    if (file_exists(__DIR__.'/patchdata/'. $contentName .'.json')) {
        $PatchData = json_decode(
            file_get_contents(__DIR__.'/patchdata/'. $contentName .'.json'),
            true
        );
    }

    $skippedArray = [];
    $skippedRecords = 0;
    $totalRecords = 0;

    // if no str columns, this is a data file, just handle it as existing in this patch
    if (!$stringColumns) {
        foreach($records as $i => $record) {
            $totalRecords++;
            $contentId = $record[0];
            $PatchData[$contentId] = $patchId;
        }
    } else {
        // detect if placeholder or not
        foreach($records as $i => $record) {
            $totalRecords++;
            $contentId = $record[0];

            // ignore those below a threshold
            if (!isPlaceholder($record, $stringColumns)) {
                $skippedArray[] = $contentId;
                $skippedRecords++;
                continue;
            }

            $PatchData[$contentId] = $patchId;
        }
    }

    reportResults($PatchData, $patchName, $totalRecords, $skippedArray, $skippedRecords, $contentName);
    saveData($contentName, $PatchData);
}

//
// MAIN PATCH, this decides which pieces of content we car eabout
//

$ContentList = [];
$PatchList = [
    [ 47, '4.35', __DIR__.'/extracts/4.35/exd/' ]
];

write('---[ PROCESSING TOP PATCH ]---');
foreach ($PatchList as $data) {
    [ $patchId, $patchName, $extractFolder] = $data;
    $files = getFileList($extractFolder);

    // process files
    write('CSV Files: '. count($files));
    write("------------------------------------------------------------");

    // start
    foreach ($files as $filename) {
        handleFile($filename, $patchId, $patchName);
    }
}

write("Content:");
$str = "\n";
foreach (array_chunk(array_keys($ContentList), 4) as $cl) {
    foreach($cl as $i => $name) {
        $cl[$i] = str_pad(substr($name, 0, 39), 40, ' ', STR_PAD_RIGHT);
    }

    $str .= implode(null, $cl) . "\n";
}
write($str);
sleep(5);

//
// PATCHES 4.32 TO 2.55
//

write('---[ PROCESSING PATCHES 4.32 > 2.55 ]---');
$PatchList = [
    # 4.X
    [ 46, '4.31', __DIR__.'/extracts/4.31/exd/' ],
    [ 45, '4.3', __DIR__.'/extracts/4.3/exd/' ],
    [ 44, '4.25', __DIR__.'/extracts/4.25/exd/' ],
    [ 43, '4.2', __DIR__.'/extracts/4.2/exd/' ],
    [ 42, '4.15', __DIR__.'/extracts/4.15/exd/' ],
    [ 41, '4.11', __DIR__.'/extracts/4.11/exd/' ],
    [ 40, '4.1', __DIR__.'/extracts/4.1/exd/' ],
    // id 39 patch 4.06 missing, i do not think anything useful was in it
    [ 38, '4.05', __DIR__.'/extracts/4.05/exd/' ],
    [ 37, '4.01', __DIR__.'/extracts/4.01/exd/' ],
    [ 36, '4.0', __DIR__.'/extracts/4.0/exd/' ],

    # 3.X
    [ 35, '3.56', __DIR__.'/extracts/3.56/exd/' ],
    [ 34, '3.55b', __DIR__.'/extracts/3.55b/exd/' ],
    [ 33, '3.55a', __DIR__.'/extracts/3.55a/exd/' ],
    [ 32, '3.5', __DIR__.'/extracts/3.5/exd/' ],
    [ 31, '3.45', __DIR__.'/extracts/3.45/exd/' ],
    [ 30, '3.4', __DIR__.'/extracts/3.4/exd/' ],

    // id 29 is missing in XIVDB patch list, maybe there is another 3.3X patch
    [ 28, '3.35', __DIR__.'/extracts/3.35/exd/' ],
    [ 27, '3.3', __DIR__.'/extracts/3.3/exd/' ],
    [ 26, '3.25', __DIR__.'/extracts/3.25/exd/' ],
    [ 25, '3.2', __DIR__.'/extracts/3.2/exd/' ],
    [ 24, '3.15', __DIR__.'/extracts/3.15/exd/' ],
    [ 23, '3.1', __DIR__.'/extracts/3.1/exd/' ],
    [ 22, '3.07', __DIR__.'/extracts/3.07/exd/' ],
    [ 21, '3.05', __DIR__.'/extracts/3.05/exd/' ],
    [ 20, '3.01', __DIR__.'/extracts/3.01/exd/' ],
    [ 19, '3.0', __DIR__.'/extracts/3.0/exd/' ],

    # 2.X
    [ 18, '2.55', __DIR__.'/extracts/2.55/exd/' ],
];

/*
foreach ($PatchList as $data) {
    [ $patchId, $patchName, $extractFolder] = $data;
    write(":::::::::::::::::::::::::::::::");
    write("[{$patchName}] :: START ". count($ContentList) ." CONTENT REMAIN ::");
    write(":::::::::::::::::::::::::::::::");

    if (!$ContentList) {
        write("[{$patchName}] No more content data?");
        die;
    }

    // loop through confirmed patch files
    foreach ($ContentList as $contentName => $state) {
        $filename = "{$extractFolder}{$contentName}.csv";

        if (!file_exists($filename)) {
            unset($ContentList[$contentName]);
            continue;
        }

        handleFile($filename, $patchId, $patchName);
    }
}
*/

//
// PATCHES 2.51 - 2.20
//

$PatchList = [
    [ 17, '2.51', __DIR__.'/extracts/2.51 - exd/exd/' ],
    [ 16, '2.50', __DIR__.'/extracts/2.50 - exd/exd/' ],
    [ 15, '2.45', __DIR__.'/extracts/2.45 - exd/exd/' ],
    [ 14, '2.40', __DIR__.'/extracts/2.40 - exd/exd/' ],
    [ 13, '2.38', __DIR__.'/extracts/2.38 - exd/exd/' ],
    [ 12, '2.35', __DIR__.'/extracts/2.35 - exd/exd/' ],
    [ 11, '2.30', __DIR__.'/extracts/2.30 - exd/exd/' ],
    [ 10, '2.28', __DIR__.'/extracts/2.28 - exd/exd/' ],
    [ 9, '2.25', __DIR__.'/extracts/2.25 - exd/exd/' ],
    [ 8, '2.20', __DIR__.'/extracts/2.20 - exd/exd/' ],
];

foreach ($PatchList as $data) {
    [ $patchId, $patchName, $extractFolder] = $data;
    write(":::::::::::::::::::::::::::::::");
    write("[{$patchName}] :: START ". count($ContentList) ." CONTENT REMAIN ::");
    write(":::::::::::::::::::::::::::::::");

    if (!$ContentList) {
        write("[{$patchName}] No more content data?");
        die;
    }

    foreach ($ContentList as $contentName => $state) {
        $filename = "{$extractFolder}{$contentName}.exd";
    }
}
