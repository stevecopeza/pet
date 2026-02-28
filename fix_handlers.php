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

    $content = file_get_contents($file);
    
    // Naive revert pattern:
    // Look for: $this->transactionManager->transactional(function () use ($command) {
    // And: });
    // And: }
    // And remove them if they look like the wrapper.
    
    // Regex to match the start of the wrapper
    $startPattern = '/(\s*)\$this->transactionManager->transactional\(function \(\) use \(\$command\) \{\s*/';
    
    // Regex to match the end of the wrapper (plus the closing brace of the method that was added/misplaced)
    // We look for }); followed by }
    $endPattern = '/\s*\}\);\s*\}\s*/';

    if (preg_match($startPattern, $content, $startMatches) && preg_match($endPattern, $content)) {
        echo "Fixing $file...\n";
        
        // Remove start
        $content = preg_replace($startPattern, '$1', $content, 1);
        
        // Remove end (replace with just whitespace or nothing, effectively merging the parts)
        // But wait, we need to be careful not to remove the closing brace of the INNER block if }); replaced it?
        // No, we established that }); replaced NOTHING in the broken case (it was appended), 
        // OR it replaced the closing brace of the method in the "correct" case.
        // In the broken case: `... inner_block } }); } rest ...`
        // We want: `... inner_block } rest ...`
        // So we remove `});` and `}`.
        // The regex `\s*\}\);\s*\}\s*` matches `});` and `}`.
        // So replacing it with `\n` or empty string is correct.
        
        $content = preg_replace($endPattern, "\n", $content, 1);
        
        // Now we have the raw body restored (mostly).
        // But we need to re-wrap it properly.
        
        // To re-wrap properly, we need to find the handle method body again.
        // Since the file might now be syntactically valid (or close to it), we can use token parsing?
        // Or we can just grab the body from `{` to the last `}` of the class minus 1.
        // Assuming handle is the last method.
        
        // Let's first save the reverted content and verify syntax.
        file_put_contents($file, $content);
        
        // Check syntax
        $output = [];
        $returnVar = 0;
        exec("php -l " . escapeshellarg($file), $output, $returnVar);
        
        if ($returnVar === 0) {
            echo "  Syntax valid after revert. Re-wrapping...\n";
            wrap_handler($file);
        } else {
            echo "  Syntax INVALID after revert. Skipping wrap.\n";
            // If invalid, maybe my revert logic was too simple.
            // But let's try.
        }
    } else {
        echo "Skipping $file (pattern not found)\n";
    }
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
            while (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) $j++;
            if (is_array($tokens[$j]) && $tokens[$j][1] === 'handle') {
                $handleStart = $i;
                // Find opening brace
                $k = $j + 1;
                while ($k < count($tokens) && $tokens[$k] !== '{') $k++;
                if ($k < count($tokens)) {
                    $braceStart = $k;
                    break;
                }
            }
        }
    }
    
    if ($braceStart !== -1) {
        // Find matching closing brace
        $balance = 1;
        for ($i = $braceStart + 1; $i < count($tokens); $i++) {
            if ($tokens[$i] === '{') $balance++;
            if ($tokens[$i] === '}') $balance--;
            if ($balance === 0) {
                $braceEnd = $i;
                break;
            }
        }
        
        if ($braceEnd !== -1) {
            // Extract body (excluding braces)
            // We need line numbers to substring? No, tokens don't give offsets easily.
            // Better to use substring logic if we can map tokens to position.
            // But we can just reconstruct the body from tokens?
            // Or use string search from the brace position?
            // That's risky with multiple braces.
            
            // Alternative: We know where { is. We can find it in string.
            // But finding the matching } in string is hard.
            
            // Let's assume the file is valid now.
            // We can just use the token positions to rebuild the file string with the wrapper.
            
            $newContent = '';
            // 0 to braceStart
            for ($j = 0; $j <= $braceStart; $j++) {
                $newContent .= is_array($tokens[$j]) ? $tokens[$j][1] : $tokens[$j];
            }
            
            $newContent .= "\n        \$this->transactionManager->transactional(function () use (\$command) {";
            
            // Body
            for ($j = $braceStart + 1; $j < $braceEnd; $j++) {
                $newContent .= is_array($tokens[$j]) ? $tokens[$j][1] : $tokens[$j];
            }
            
            $newContent .= "\n        });";
            
            // braceEnd to end
            for ($j = $braceEnd; $j < count($tokens); $j++) {
                $newContent .= is_array($tokens[$j]) ? $tokens[$j][1] : $tokens[$j];
            }
            
            file_put_contents($file, $newContent);
            echo "  Wrapped successfully.\n";
        }
    }
}
