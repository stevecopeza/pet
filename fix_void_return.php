<?php

$directory = __DIR__ . '/src/Application';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

echo "Scanning $directory...\n";
$count = 0;
foreach ($iterator as $file) {
    if ($file->isDir()) continue;
    
    $filePath = $file->getPathname();
    if (!str_ends_with($filePath, 'Handler.php')) continue;

    $count++;
    $content = file_get_contents($filePath);
    
    if (strpos($content, 'CreateProjectHandler') !== false) {
        echo "Found CreateProjectHandler at $filePath\n";
        if (preg_match('/public function handle\s*\(.*?\)\s*:\s*void/s', $content)) {
            echo "  handle is void\n";
        } else {
            echo "  handle is NOT void\n";
        }
        if (strpos($content, 'return $this->transactionManager->transactional') !== false) {
            echo "  Has return transactional\n";
        } else {
            echo "  Does NOT have return transactional\n";
        }
    }

    if (preg_match('/public function handle\s*\(.*?\)\s*:\s*void/s', $content)) {
        if (strpos($content, 'return $this->transactionManager->transactional') !== false) {
            echo "Fixing void return in $filePath...\n";
            $content = str_replace(
                'return $this->transactionManager->transactional',
                '$this->transactionManager->transactional',
                $content
            );
            file_put_contents($filePath, $content);
        }
    }
}
echo "Scanned $count handlers.\n";
