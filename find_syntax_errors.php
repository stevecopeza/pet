<?php

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator('src')
);

foreach ($iterator as $file) {
    if ($file->getExtension() === 'php') {
        $output = [];
        $returnVar = 0;
        exec("php -l " . escapeshellarg($file->getPathname()) . " 2>&1", $output, $returnVar);
        if ($returnVar !== 0) {
            echo "Error in: " . $file->getPathname() . "\n";
            echo implode("\n", array_slice($output, 0, 5)) . "\n\n";
        }
    }
}
