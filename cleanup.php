<?php

/**
 * This script converts "all raw exd" files to a "rawexd" equivalent.
 */

$allrawexds = [
    __DIR__.'/extracts/6.15',
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
