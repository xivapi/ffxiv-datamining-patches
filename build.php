<?php

/**
 * This works by processing the patch values for the most current patch and keeping a record of all "current"
 * content files. It will then loop through all previous patches and if the file exists + the row then the
 * previous patch ID is assigned. If the content file is not found for that patch it is removed from the list
 * and stops updating (completed file)
 *
 * The placeholder detection simply removes all characters except for letters in the top 10 string rows and
 * if any are non-empty it assumes it is a non-placeholder and assigns a patch value.
 *
 * We can fix any weird ones on a case-by-case basis.
 *
 * PS. This is some major hacky PHP and i don't care, it's just to generate a list once :)
 */

require_once __DIR__ . '/vendor/autoload.php';

// write("Resetting");
sleep(1);
unlink(__DIR__ . '/log.txt');
sleep(2);

use League\Csv\Reader;
use League\Csv\Statement;

$full = $argv[1] == '--full';

function write($text)
{
    $date = date('Y-m-d H:i:s');
    $text = "[{$date}] {$text} \n";
    file_put_contents(__DIR__ . '/log.txt', $text, FILE_APPEND);
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
        if (count($stringColumns) == 10) {
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
        __DIR__ . '/patchdata/' . $contentName . '.json',
        json_encode($data, JSON_PRETTY_PRINT)
    );
}

function isPlaceholder($record, $stringColumns)
{
    foreach ($stringColumns as $i => $index) {
        // If at least 1 string value has characters
        if (!isset($record[$index])) {
            unset($stringColumns[$i]);
            continue;
        }

        $value = trim($record[$index]);
        $value = preg_replace('/\PL/u', '', $value);

        if (strlen($value) > 1) {
            return false;
        }
    }

    return true;
}

function reportResults($PatchData, $patchName, $totalRecords, $skippedArray, $skippedRecords, $contentName)
{
    $totalSkipped = $skippedRecords == 0 ? 0 : round(($skippedRecords / $totalRecords * 100), 1);

    write("[{$patchName}] " . count($PatchData) . "/{$totalRecords} Assigned {$skippedRecords}/{$totalRecords} ({$totalSkipped}%) Placeholders");

    if ($totalSkipped > 50) {
        write("[{$patchName}] !!! Over 30% of rows were skipped, check the IDs in the CSV just to be sure");
        write("[{$patchName}] !!! You can run this manually via: php build.php {$contentName}");
    }

    write("------------------------------------------------------------");
}

function handleFile($filename, $patchId, $patchName)
{
    global $ContentList, $full;

    $pathInfo    = pathinfo($filename);
    $contentName = $pathInfo['filename'];
    $ContentList[$contentName] = true;

    write("[{$patchName}] CSV {$contentName}");

    [$records, $stringColumns] = getCsvData($filename);

    $PatchData = [];

    // load existing patch data if it exists
    if (file_exists(__DIR__ . '/patchdata/' . $contentName . '.json')) {
        $PatchData = json_decode(
            file_get_contents(__DIR__ . '/patchdata/' . $contentName . '.json'),
            true
        );
    }

    $skippedArray = [];
    $skippedRecords = 0;
    $totalRecords = 0;

    // if no str columns, this is a data file, just handle it as existing in this patch
    if (!$stringColumns) {
        foreach ($records as $i => $record) {
            $totalRecords++;
            $contentId = $record[0];
            if ($full || !isset($PatchData[$contentId])) {
                $PatchData[$contentId] = $patchId;
            }
        }
    } else {
        // detect if placeholder or not
        foreach ($records as $i => $record) {
            $totalRecords++;
            $contentId = $record[0];

            // ignore those below a threshold
            if (isPlaceholder($record, $stringColumns)) {
                $skippedArray[] = $contentId;
                $skippedRecords++;
                continue;
            }

            if ($full || !isset($PatchData[$contentId])) {
                $PatchData[$contentId] = $patchId;
            }
        }
    }

    reportResults($PatchData, $patchName, $totalRecords, $skippedArray, $skippedRecords, $contentName);
    saveData($contentName, $PatchData);
}

//
// MAIN PATCH, this decides which pieces of content we care about
// this should always be the latest patchdata

$ContentList = [];
$PatchList = [
    [90, '6.48', __DIR__ . '/extracts/6.48/'],
];

write('---[ PROCESSING TOP PATCH ]---');
foreach ($PatchList as $data) {
    [$patchId, $patchName, $extractFolder] = $data;
    $files = getFileList($extractFolder);

    // process files
    write('CSV Files: ' . count($files));
    write("------------------------------------------------------------");

    // start
    foreach ($files as $filename) {
        handleFile($filename, $patchId, $patchName);
    }
}

if ($full) {

    write("Content:");
    $str = "\n";
    foreach (array_chunk(array_keys($ContentList), 4) as $cl) {
        foreach ($cl as $i => $name) {
            $cl[$i] = str_pad(substr($name, 0, 39), 40, ' ', STR_PAD_RIGHT);
        }

        $str .= implode(null, $cl) . "\n";
    }
    write($str);

    write("Staring in 3...");
    sleep(1);
    write("Staring in 2...");
    sleep(1);
    write("Staring in 1...");
    sleep(1);

    //
    // PATCHES Latest TO 2.55
    //

    write('---[ PROCESSING PATCHES Latest > 2.55 ]---');
    $PatchList = [
        [89, '6.45', __DIR__ . '/extracts/6.45/'],
        [88, '6.4', __DIR__ . '/extracts/6.4/'],
        [87, '6.35', __DIR__ . '/extracts/6.35/'],
        [86, '6.3', __DIR__ . '/extracts/6.3/'],
        # 6.X
        [85, '6.28', __DIR__ . '/extracts/6.28/'],
        [84, '6.25', __DIR__ . '/extracts/6.25/'],
        [83, '6.2', __DIR__ . '/extracts/6.2/'],
        [82, '6.15', __DIR__ . '/extracts/6.15/'],
        [81, '6.11', __DIR__ . '/extracts/6.11/'],
        [80, '6.1', __DIR__ . '/extracts/6.1/'],
        [79, '6.05', __DIR__ . '/extracts/6.05/'],
        [78, '6.01', __DIR__ . '/extracts/6.01/'],
        [77, '6.0', __DIR__ . '/extracts/6.0/'],
        # 5.X
        [76, '5.55', __DIR__ . '/extracts/5.55/'],
        [75, '5.5', __DIR__ . '/extracts/5.5/'],
        [74, '5.45', __DIR__ . '/extracts/5.45/'],
        [73, '5.41', __DIR__ . '/extracts/5.41/'],
        [72, '5.4', __DIR__ . '/extracts/5.4/'],
        [71, '5.35', __DIR__ . '/extracts/5.35/'],
        [70, '5.31', __DIR__ . '/extracts/5.31/'],
        [69, '5.3', __DIR__ . '/extracts/5.3/'],
        [68, '5.25', __DIR__ . '/extracts/5.25/'],
        [67, '5.2', __DIR__ . '/extracts/5.21/'],
        [66, '5.2', __DIR__ . '/extracts/5.2/'],
        [65, '5.18', __DIR__ . '/extracts/5.18/'],
        [63, '5.11', __DIR__ . '/extracts/5.11/'],
        [62, '5.1', __DIR__ . '/extracts/5.1/'],
        [61, '5.08', __DIR__ . '/extracts/5.05/'],
        [60, '5.05', __DIR__ . '/extracts/5.05/'],
        [59, '5.01', __DIR__ . '/extracts/5.01/'],
        [58, '5.0', __DIR__ . '/extracts/5.0/'],
        # 4.X
        [57, '4.58', __DIR__ . '/extracts/4.58/'],
        [56, '4.57', __DIR__ . '/extracts/4.57/'],
        [55, '4.56', __DIR__ . '/extracts/4.56/'],
        [54, '4.55', __DIR__ . '/extracts/4.55/'],
        [52, '4.5', __DIR__ . '/extracts/4.5/'],
        [51, '4.45', __DIR__ . '/extracts/4.45/'],
        [50, '4.41', __DIR__ . '/extracts/4.41/'],
        [49, '4.4', __DIR__ . '/extracts/4.4/'],
        // no extract for 4.36
        [47, '4.35', __DIR__ . '/extracts/4.35/'],
        [46, '4.31', __DIR__ . '/extracts/4.31/'],
        [45, '4.3', __DIR__ . '/extracts/4.3/'],
        [44, '4.25', __DIR__ . '/extracts/4.25/'],
        [43, '4.2', __DIR__ . '/extracts/4.2/'],
        [42, '4.15', __DIR__ . '/extracts/4.15/'],
        [41, '4.11', __DIR__ . '/extracts/4.11/'],
        [40, '4.1', __DIR__ . '/extracts/4.1/'],
        // id 39 patch 4.06 missing, i do not think anything useful was in it
        [38, '4.05', __DIR__ . '/extracts/4.05/'],
        [37, '4.01', __DIR__ . '/extracts/4.01/'],
        [36, '4.0', __DIR__ . '/extracts/4.0/'],

        # 3.X
        [35, '3.56', __DIR__ . '/extracts/3.56/'],
        [34, '3.55b', __DIR__ . '/extracts/3.55b/'],
        [33, '3.55a', __DIR__ . '/extracts/3.55a/'],
        [32, '3.5', __DIR__ . '/extracts/3.5/'],
        [31, '3.45', __DIR__ . '/extracts/3.45/'],
        [30, '3.4', __DIR__ . '/extracts/3.4/'],

        // id 29 is missing in XIVDB patch list, maybe there is another 3.3X patch
        [28, '3.35', __DIR__ . '/extracts/3.35/'],
        [27, '3.3', __DIR__ . '/extracts/3.3/'],
        [26, '3.25', __DIR__ . '/extracts/3.25/'],
        [25, '3.2', __DIR__ . '/extracts/3.2/'],
        [24, '3.15', __DIR__ . '/extracts/3.15/'],
        [23, '3.1', __DIR__ . '/extracts/3.1/'],
        [22, '3.07', __DIR__ . '/extracts/3.07/'],
        [21, '3.05', __DIR__ . '/extracts/3.05/'],
        [20, '3.01', __DIR__ . '/extracts/3.01/'],
        [19, '3.0', __DIR__ . '/extracts/3.0/'],

        # 2.X
        [18, '2.55', __DIR__ . '/extracts/2.55/'],
    ];

    foreach ($PatchList as $data) {
        [$patchId, $patchName, $extractFolder] = $data;
        write(":::::::::::::::::::::::::::::::");
        write("[{$patchName}] :: START " . count($ContentList) . " CONTENT REMAIN ::");
        write(":::::::::::::::::::::::::::::::");

        if (!$ContentList) {
            write("[{$patchName}] No more content data?");
            die;
        }

        // loop through confirmed patch files
        foreach ($ContentList as $contentName => $state) {
            $filename = "{$extractFolder}{$contentName}.csv";

            if (!file_exists($filename)) {
                write("[{$patchName}] {$contentName} not part of this patch, added in next patch");
                unset($ContentList[$contentName]);
                continue;
            }

            handleFile($filename, $patchId, $patchName);
        }
    }

    //
    // PATCHES 2.51 - 2.20
    //

    write('---------------------------------------------------------');
    write('');
    write('Processing older patches');
    write('');
    write('---------------------------------------------------------');

    write('---[ PROCESSING PATCHES 2.51 > 2.2 ]---');

    $PatchList = [
        [17, '2.51', __DIR__ . '/extracts/2.51/'],
        [16, '2.50', __DIR__ . '/extracts/2.50/'],
        [15, '2.45', __DIR__ . '/extracts/2.45/'],
        [14, '2.40', __DIR__ . '/extracts/2.40/'],
        [13, '2.38', __DIR__ . '/extracts/2.38/'],
        [12, '2.35', __DIR__ . '/extracts/2.35/'],
        [11, '2.30', __DIR__ . '/extracts/2.30/'],
        [10, '2.28', __DIR__ . '/extracts/2.28/'],
        [9, '2.25', __DIR__ . '/extracts/2.25/'],
        [8, '2.20', __DIR__ . '/extracts/2.20/'],
        [4, '2.10', __DIR__ . '/extracts/2.10/'],
        [2, '2.0', __DIR__ . '/extracts/2.00/'],
    ];

    foreach ($PatchList as $data) {
        [$patchId, $patchName, $extractFolder] = $data;
        write(":::::::::::::::::::::::::::::::");
        write("[{$patchName}] :: START " . count($ContentList) . " CONTENT REMAIN ::");
        write(":::::::::::::::::::::::::::::::");

        if (!$ContentList) {
            write("[{$patchName}] No more content data?");
            die;
        }

        // loop through content
        foreach ($ContentList as $contentName => $state) {
            write("[{$patchName}] CSV {$contentName}");
            $filename = "{$extractFolder}{$contentName}.exd";

            // skip if not forced
            if ($forceContentName && $forceContentName != $contentName) {
                continue;
            }

            // file does not exist, was added after this patch
            if (!file_exists($filename)) {
                write("[{$patchName}] {$contentName} not part of this patch, added in next patch");
                unset($ContentList[$contentName]);
                continue;
            }

            // get CSV records
            $csv = Reader::createFromPath($filename);
            $csvRecords = (new Statement())->offset(0)->process($csv)->getRecords();

            // find string columns throughout the entire system using the hack {{{STRING}}} placeholder
            // we only care about the first 5 string values
            $stringColumns = [];
            $csvArray = [];
            foreach ($csvRecords as $record) {
                $csvArray[] = $record;
                foreach ($record as $index => $value) {
                    if (stripos($value, '{{{STRING}}}') !== false) {
                        $stringColumns[$index] = true;
                    }
                }
            }

            $stringColumns = array_keys($stringColumns);
            array_splice($stringColumns, 10);

            // load existing patch data if it exists
            $PatchData = [];
            if (file_exists(__DIR__ . '/patchdata/' . $contentName . '.json')) {
                $PatchData = json_decode(
                    file_get_contents(__DIR__ . '/patchdata/' . $contentName . '.json'),
                    true
                );
            }

            $skippedArray = [];
            $skippedRecords = 0;
            $totalRecords = 0;

            // if no str columns, this is a data file, just handle it as existing in this patch
            if (!$stringColumns) {
                foreach ($csvArray as $i => $record) {
                    $totalRecords++;
                    $contentId = $record[0];
                    $PatchData[$contentId] = $patchId;
                }
            } else {
                // detect if placeholder or not
                foreach ($csvArray as $i => $record) {
                    $totalRecords++;
                    $contentId = $record[0];

                    // ignore those below a threshold, unless the patch is 2 then it's 2.0!
                    if (isPlaceholder($record, $stringColumns) && $patchId !== 2) {
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
    }
}
