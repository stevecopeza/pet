<?php

$directory = __DIR__ . '/src/Application';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
$regex = new RegexIterator($iterator, '/^.+Handler\.php$/i', RecursiveRegexIterator::GET_MATCH);

foreach ($regex as $file) {
    $filePath = $file[0];
    $content = file_get_contents($filePath);

    // Skip if already updated or interface/abstract
    if (strpos($content, 'TransactionManager') !== false || strpos($content, 'interface ') !== false || strpos($content, 'abstract class ') !== false) {
        continue;
    }

    echo "Updating $filePath...\n";

    // Step 1: Add use statement
    $namespaceEnd = strpos($content, ';', strpos($content, 'namespace '));
    $content = substr_replace($content, "\n\nuse Pet\\Application\\System\\Service\\TransactionManager;", $namespaceEnd + 1, 0);

    // Step 2: Add Property
    if (preg_match('/class\s+(\w+).*?\{/s', $content, $matches)) {
        $className = $matches[1];
        $classStartPos = strpos($content, '{', strpos($content, "class $className"));
        $content = substr_replace($content, "\n    private TransactionManager \$transactionManager;", $classStartPos + 1, 0);
    }

    // Step 3: Add Constructor
    if (preg_match('/public function __construct\s*\((.*?)\)\s*\{/s', $content, $matches)) {
        $args = $matches[1];
        // We need to be careful with replacement to not break the file structure
        $oldConstruct = $matches[0];
        
        if (trim($args) === '') {
            $newArgs = 'TransactionManager $transactionManager';
        } else {
            $newArgs = 'TransactionManager $transactionManager, ' . $args;
        }
        
        $newConstruct = str_replace($args, $newArgs, $oldConstruct);
        // Add assignment
        $pos = strpos($newConstruct, '{');
        $newConstruct = substr_replace($newConstruct, "\n        \$this->transactionManager = \$transactionManager;", $pos + 1, 0);
        
        $content = str_replace($oldConstruct, $newConstruct, $content);
    } else {
        // No constructor, add one
        $classStartPos = strpos($content, '{', strpos($content, "class $className"));
        // Insert after property
        $insertPos = strpos($content, ';', $classStartPos) + 1;
        $constructor = "\n\n    public function __construct(TransactionManager \$transactionManager)\n    {\n        \$this->transactionManager = \$transactionManager;\n    }";
        $content = substr_replace($content, $constructor, $insertPos, 0);
    }

    // Step 4: Wrap handle method
    // Re-tokenize the modified content
    $tokens = token_get_all($content);
    $output = '';
    $inHandle = false;
    $handleBraceLevel = 0;
    $handleArgs = [];
    
    // Scan for handle method first to get args
    // Actually, we can do it in one pass if we are careful.
    
    for ($i = 0; $i < count($tokens); $i++) {
        $token = $tokens[$i];
        $text = is_array($token) ? $token[1] : $token;
        $id = is_array($token) ? $token[0] : null;
        
        // Detect handle method start
        if ($id === T_FUNCTION && !$inHandle) {
            // Look ahead for 'handle'
            $j = $i + 1;
            while (isset($tokens[$j]) && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) $j++;
            
            if (isset($tokens[$j]) && is_array($tokens[$j]) && $tokens[$j][1] === 'handle') {
                $inHandle = true;
                $handleBraceLevel = 0;
                $handleArgs = [];
                
                // Extract args
                $k = $j + 1;
                while ($tokens[$k] !== '(') $k++;
                $k++; // inside (
                while ($tokens[$k] !== ')') {
                    if (is_array($tokens[$k]) && $tokens[$k][0] === T_VARIABLE) {
                        $handleArgs[] = $tokens[$k][1];
                    }
                    $k++;
                }
            }
        }
        
        // Handle brace counting
        if ($inHandle) {
            if ($text === '{' || $id === T_CURLY_OPEN || $id === T_DOLLAR_OPEN_CURLY_BRACES) {
                $handleBraceLevel++;
                $output .= $text;
                
                if ($handleBraceLevel === 1 && $text === '{') {
                    // Start of handle body
                    $useVars = implode(', ', $handleArgs);
                    // No return, as it is void
                    $output .= "\n        \$this->transactionManager->transactional(function () use ($useVars) {";
                }
            } elseif ($text === '}') {
                $handleBraceLevel--;
                
                if ($handleBraceLevel === 0) {
                    $inHandle = false;
                    // End of handle body
                    $output .= "\n        });\n    }";
                } else {
                    $output .= $text;
                }
            } else {
                $output .= $text;
            }
        } else {
            $output .= $text;
        }
    }
    
    file_put_contents($filePath, $output);
}
