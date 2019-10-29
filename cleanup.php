<?php

/**
 * This script converts "all raw exd" files to a "rawexd" equivalent.
 */

$allrawexds = [
    __DIR__.'/extracts/4.5',
    __DIR__.'/extracts/4.55',
    __DIR__.'/extracts/4.56',
    __DIR__.'/extracts/4.57',
    __DIR__.'/extracts/4.58',
    __DIR__.'/extracts/5.0',
    __DIR__.'/extracts/5.01',
    __DIR__.'/extracts/5.05',
    __DIR__.'/extracts/5.08',
    __DIR__.'/extracts/5.1',
];

foreach ($allrawexds as $directory) {
    $files = scandir($directory);
    
    foreach ($files as $file) {
        // remove german, french and japanese
        if (stripos($file, '.de.csv') !== false || stripos($file, '.fr.csv') !== false || stripos($file, '.ja.csv') !== false) {
            unlink($directory .'/'. $file);
        }
        
        // rename english to normal
        if (stripos($file, '.en.csv') !== false) {
            rename(
                $directory .'/'. $file,
                $directory .'/'. str_ireplace('.en.csv', '.csv', $file)
            );
        }
    }
}

echo "\nDone\n";
