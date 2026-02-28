<?php

$files = [
    'src/Application/Identity/Command/ArchiveContactHandler.php',
    'src/Application/Commercial/Command/AddComponentHandler.php',
    'src/Application/Commercial/Command/CreateLeadHandler.php',
    'src/Application/Commercial/Command/AddQuoteSectionHandler.php',
    'src/Application/Commercial/Command/RemoveComponentHandler.php',
    'src/Application/Commercial/Command/SendQuoteHandler.php',
    'src/Application/Commercial/Command/UpdateLeadHandler.php',
    'src/Application/Commercial/Command/AddQuoteLineHandler.php',
    'src/Application/Commercial/Command/CreateQuoteHandler.php',
    'src/Application/Time/Command/LogTimeHandler.php',
    'src/Application/Team/Command/UpdateTeamHandler.php',
    'src/Application/Support/Command/CreateTicketHandler.php',
    'src/Application/Delivery/Command/AddTaskHandler.php',
    'src/Application/Delivery/Command/CreateProjectHandler.php',
    'src/Application/Identity/Command/UpdateContactHandler.php',
];

foreach ($files as $file) {
    if (!file_exists($file)) {
        echo "File not found: $file\n";
        continue;
    }

    echo "Processing $file...\n";
    $content = file_get_contents($file);
    $originalContent = $content;

    // 1. Fix missing braces after throw
    // Pattern: if (...) {\n ... throw ...;\n [something that is not }]
    // We look for: throw ...; followed by newline, then non-brace.
    
    $lines = explode("\n", $content);
    $newLines = [];
    $modified = false;

    for ($i = 0; $i < count($lines); $i++) {
        $line = $lines[$i];
        $newLines[] = $line;

        // Check if this line is a throw statement inside an if (heuristic: indented)
        if (preg_match('/^\s+throw new .+;$/', $line)) {
            // Check next line (ignoring comments/empty lines?)
            // In the observed cases, the next line is code or comment, but definitely NOT '}'
            
            $j = $i + 1;
            // Skip empty lines
            while ($j < count($lines) && trim($lines[$j]) === '') {
                $j++;
            }
            
            if ($j < count($lines)) {
                $nextLine = $lines[$j];
                $trimmedNext = trim($nextLine);
                
                // If next line is NOT '}' (and not empty which we skipped)
                if ($trimmedNext !== '}' && $trimmedNext !== '},') {
                    // Check if the throw was inside a block that needs closing.
                    // We assume standard indentation: 4 spaces per level.
                    // throw line indentation
                    preg_match('/^(\s+)/', $line, $matches);
                    $indent = $matches[1] ?? '';
                    $indentLen = strlen($indent);
                    
                    // Proposed brace indentation
                    $braceIndentLen = max(0, $indentLen - 4);
                    $braceIndent = str_repeat(' ', $braceIndentLen);
                    
                    // Heuristic: Check if the line BEFORE the throw was an 'if' or '{'
                    // Scan backwards
                    $k = $i - 1;
                    while ($k >= 0 && trim($lines[$k]) === '') $k--;
                    
                    if ($k >= 0 && str_ends_with(trim($lines[$k]), '{')) {
                        // Yes, it was an open block.
                        // And the next line (j) is NOT closing it.
                        // So we insert a brace.
                        echo "  [Fix] Inserting missing brace after line " . ($i+1) . "\n";
                        $newLines[] = $braceIndent . "}";
                        $modified = true;
                    }
                }
            }
        }
    }

    if ($modified) {
        $content = implode("\n", $newLines);
        file_put_contents($file, $content);
        echo "  Saved syntax fix.\n";
    }

    // 2. Wrap in transaction
    // First, check syntax
    $output = [];
    $returnVar = 0;
    exec("php -l " . escapeshellarg($file), $output, $returnVar);
    
    if ($returnVar !== 0) {
        echo "  [Error] Syntax invalid after fix attempt. Skipping transaction wrap.\n";
        echo implode("\n", $output) . "\n";
        continue;
    }

    // Check if already wrapped
    if (strpos($content, '$this->transactionManager->transactional') !== false) {
        echo "  Already wrapped.\n";
        continue;
    }

    echo "  Wrapping in transaction...\n";
    wrap_handler($file);
}

function wrap_handler($file) {
    $content = file_get_contents($file);
    $tokens = token_get_all($content);
    
    $handleStart = -1;
    $braceStart = -1;
    $braceEnd = -1;
    
    // Find function handle
    for ($i = 0; $i < count($tokens); $i++) {
        if (is_array($tokens[$i]) && $tokens[$i][0] === T_FUNCTION) {
            // Check next token for 'handle'
            $j = $i + 1;
            while (isset($tokens[$j]) && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) $j++;
            
            if (isset($tokens[$j]) && is_array($tokens[$j]) && $tokens[$j][1] === 'handle') {
                $handleStart = $i;
                
                // Find opening brace
                $k = $j + 1;
                while (isset($tokens[$k]) && $tokens[$k] !== '{') $k++;
                
                if (isset($tokens[$k]) && $tokens[$k] === '{') {
                    $braceStart = $k;
                    
                    // Find matching closing brace
                    // Count braces
                    $depth = 1;
                    $l = $k + 1;
                    while ($l < count($tokens) && $depth > 0) {
                        if ($tokens[$l] === '{') $depth++;
                        if ($tokens[$l] === '}') $depth--;
                        if ($depth === 0) {
                            $braceEnd = $l;
                            break;
                        }
                        $l++;
                    }
                    break;
                }
            }
        }
    }
    
    if ($braceStart !== -1 && $braceEnd !== -1) {
        // Reconstruct content
        $newContent = '';
        
        // Before body (including opening brace)
        // We want to insert AFTER the opening brace.
        
        // Actually, token_get_all doesn't give us easy positions in the string unless we track line numbers or reconstruct.
        // Reconstructing from tokens is safer.
        
        for ($i = 0; $i <= $braceStart; $i++) {
            $t = $tokens[$i];
            $newContent .= is_array($t) ? $t[1] : $t;
        }
        
        $newContent .= "\n        \$this->transactionManager->transactional(function () use (\$command) {";
        
        // Body
        for ($i = $braceStart + 1; $i < $braceEnd; $i++) {
             $t = $tokens[$i];
             $newContent .= is_array($t) ? $t[1] : $t;
        }
        
        // Close wrapper
        $newContent .= "\n        });";
        
        // Closing brace of method
        $newContent .= "\n    }";
        
        // Rest of file
        for ($i = $braceEnd + 1; $i < count($tokens); $i++) {
             $t = $tokens[$i];
             $newContent .= is_array($t) ? $t[1] : $t;
        }
        
        file_put_contents($file, $newContent);
        echo "  Wrapped successfully.\n";
        
    } else {
        echo "  [Error] Could not find handle method body.\n";
    }
}
