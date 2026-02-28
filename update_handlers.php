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

    $tokens = token_get_all($content);
    $newContent = '';
    $inClass = false;
    $inHandle = false;
    $braceLevel = 0;
    $handleStartBraceLevel = 0;
    $handleArgsVars = [];
    $className = '';

    // First pass: check structure and gather info
    // We need to insert use statement, property, constructor arg/assignment, wrap handle.
    
    // Simplification: We will modify the string based on token positions.
    // But modifying string while iterating tokens is tricky due to offsets shifting.
    // So we rebuild the string token by token, injecting content where needed.
    
    // Step 1: Add use statement
    $namespaceEnd = strpos($content, ';', strpos($content, 'namespace '));
    $content = substr_replace($content, "\n\nuse Pet\\Application\\System\\Service\\TransactionManager;", $namespaceEnd + 1, 0);

    // Re-tokenize after modification? No, just keep offsets relative or do regex for simple parts first.
    // Let's use regex for use statement and property/constructor.
    
    // Add Property
    if (preg_match('/class\s+(\w+).*?\{/s', $content, $matches)) {
        $className = $matches[1];
        $classStartPos = strpos($content, '{', strpos($content, "class $className"));
        $content = substr_replace($content, "\n    private TransactionManager \$transactionManager;", $classStartPos + 1, 0);
    }

    // Add Constructor
    if (preg_match('/public function __construct\s*\((.*?)\)\s*\{/s', $content, $matches)) {
        $args = $matches[1];
        $constructStart = strpos($content, '__construct');
        $argsStart = strpos($content, '(', $constructStart);
        $argsEnd = strpos($content, ')', $argsStart);
        $bodyStart = strpos($content, '{', $argsEnd);
        
        // Add argument
        if (trim($args) === '') {
            $newArgs = 'TransactionManager $transactionManager';
        } else {
            $newArgs = 'TransactionManager $transactionManager, ' . $args;
        }
        
        // We need to replace the args part in content
        // But finding exact position with regex match is hard.
        // Let's use string replacement on the match.
        $oldConstruct = $matches[0];
        $newConstruct = str_replace($args, $newArgs, $oldConstruct);
        $newConstruct = str_replace('{', "{\n        \$this->transactionManager = \$transactionManager;", $newConstruct);
        
        $content = str_replace($oldConstruct, $newConstruct, $content);
    } else {
        // No constructor, add one
        // Find class start again (after property insertion)
        $classStartPos = strpos($content, '{', strpos($content, "class $className"));
        // Insert after property
        $insertPos = strpos($content, ';', $classStartPos) + 1;
        $constructor = "\n\n    public function __construct(TransactionManager \$transactionManager)\n    {\n        \$this->transactionManager = \$transactionManager;\n    }";
        $content = substr_replace($content, $constructor, $insertPos, 0);
    }

    // Now wrap handle method using token parser on the modified content
    $tokens = token_get_all($content);
    $output = '';
    $inHandle = false;
    $handleBraceLevel = 0;
    $handleBodyStart = false;
    $handleArgs = [];

    for ($i = 0; $i < count($tokens); $i++) {
        $token = $tokens[$i];
        
        if (is_array($token)) {
            $text = $token[1];
            
            // Detect handle method
            if ($token[0] === T_FUNCTION) {
                // Check next token for 'handle'
                $j = $i + 1;
                while (isset($tokens[$j]) && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) $j++;
                if (isset($tokens[$j]) && is_array($tokens[$j]) && $tokens[$j][1] === 'handle') {
                    $inHandle = true;
                    $handleBraceLevel = 0;
                    $handleBodyStart = false;
                    
                    // Capture arguments
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
            
            $output .= $text;
        } else {
            $text = $token;
            $output .= $text;
            
            if ($inHandle) {
                if ($text === '{') {
                    $handleBraceLevel++;
                    if ($handleBraceLevel === 1) {
                        $handleBodyStart = true;
                        // Inject start of transaction
                        $useVars = implode(', ', $handleArgs);
                        $output .= "\n        return \$this->transactionManager->transactional(function () use ($useVars) {";
                    }
                } elseif ($text === '}') {
                    $handleBraceLevel--;
                    if ($handleBraceLevel === 0) {
                        $inHandle = false;
                        // Inject end of transaction
                        // Remove the last brace from output first? No, we append BEFORE it.
                        // Wait, we just appended '}'. We need to inject BEFORE '}'.
                        $output = substr($output, 0, -1); 
                        $output .= "\n        });\n    }";
                    }
                }
            }
        }
    }
    
    file_put_contents($filePath, $output);
}
