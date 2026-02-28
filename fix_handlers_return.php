<?php
$files = glob(__DIR__ . '/src/Application/**/*Handler.php');
// Also check nested directories
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/src/Application'));
$files = [];
foreach ($iterator as $file) {
    if ($file->isFile() && strpos($file->getFilename(), 'Handler.php') !== false) {
        $files[] = $file->getPathname();
    }
}

foreach ($files as $file) {
    $content = file_get_contents($file);
    
    // Check if handle method has void return type
    // Matches: public function handle(...) : void
    // or public function handle(...):void
    // or public function handle(...)
    // : void
    if (preg_match('/public\s+function\s+handle\s*\([^)]*\)\s*:\s*void/i', $content)) {
        // It's a void function.
        // Check if we have "return $this->transactionManager->transactional"
        if (strpos($content, 'return $this->transactionManager->transactional') !== false) {
            $newContent = str_replace(
                'return $this->transactionManager->transactional', 
                '$this->transactionManager->transactional', 
                $content
            );
            file_put_contents($file, $newContent);
            echo "Fixed void return in: $file\n";
        }
    }
}
