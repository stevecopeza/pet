<?php

$directory = __DIR__ . '/src/Application';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

echo "Scanning for non-void handlers...\n";
foreach ($iterator as $file) {
    if ($file->isDir()) continue;
    $filePath = $file->getPathname();
    if (!str_ends_with($filePath, 'Handler.php')) continue;

    $content = file_get_contents($filePath);
    
    // Check if handle method has a return type that is NOT void
    if (preg_match('/public function handle\s*\(.*?\)\s*:\s*([a-zA-Z\\\\]+)/', $content, $matches)) {
        $returnType = trim($matches[1]);
        if (strtolower($returnType) !== 'void') {
            echo "Found non-void handler: $filePath (returns $returnType)\n";
            
            // Check if it has 'return $this->transactionManager->transactional'
            if (strpos($content, 'return $this->transactionManager->transactional') === false) {
                echo "  MISSING return before transactional!\n";
                // Fix it
                $content = str_replace(
                    '$this->transactionManager->transactional',
                    'return $this->transactionManager->transactional',
                    $content
                );
                file_put_contents($filePath, $content);
                echo "  Fixed.\n";
            } else {
                echo "  Already correct.\n";
            }
        }
    }
}
