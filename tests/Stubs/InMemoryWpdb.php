<?php

namespace Pet\Tests\Stubs;

/** 
 * UNIT TEST STUB ONLY. 
 * 
 * This stub parses SQL via regex. It supports basic SELECT, INSERT, 
 * UPDATE, DELETE patterns only. It does not and cannot support: 
 *   - SELECT ... FOR UPDATE 
 *   - JOIN of any kind 
 *   - Subqueries 
 *   - OFFSET 
 *   - IN (...) with multiple values 
 *   - IS NULL / IS NOT NULL 
 *   - ON DUPLICATE KEY UPDATE 
 * 
 * Any test that requires the above must use a real database connection. 
 * Do not add regex patterns for the above to this file. 
 */

use wpdb;

if (!class_exists('Pet\Tests\Stubs\InMemoryWpdb')) {
    class InMemoryWpdb extends wpdb
    {
        public $table_data = [];
    public $table_schema = []; // key = table name, value = array of column names
    public $insert_id = 0;
    public $auto_increment = []; // key = table name, value = next auto increment id
    public $prefix = 'wp_';
    public $last_error = '';
    public $last_query = '';
    public $insertCountByTable = [];
    public $lastInsertIdByTable = [];
    public $transactionStatus = []; // Log of transaction commands: ['START TRANSACTION', 'COMMIT', 'ROLLBACK']
    public $query_log = [];

    const ARRAY_A = 'ARRAY_A';
    const OBJECT = 'OBJECT';

        public function __construct()
        {
            // No-op or init logic
        }

        public function query($query)
        {
            $this->last_query = $query;
            $query = trim($query);
            
            // Handle CREATE TABLE
            if (stripos($query, 'CREATE TABLE') === 0) {
                if (preg_match('/CREATE TABLE\s+(?:IF NOT EXISTS\s+)?([^\s(]+)/i', $query, $matches)) {
                    $tableName = trim($matches[1], '`');
                    $this->table_data[$tableName] = [];
                    return true;
                }
            }

            // Handle INSERT
            if (stripos($query, 'INSERT INTO') === 0) {
            // Check for INSERT INTO ... ON DUPLICATE KEY UPDATE
            $onDuplicate = false;
            if (stripos($query, 'ON DUPLICATE KEY UPDATE') !== false) {
                $onDuplicate = true;
            }

            // Try to parse INSERT INTO table (col1, col2) VALUES (val1, val2)
                if (preg_match('/INSERT INTO\s+([^\s(]+)\s*\(([^)]+)\)\s*VALUES\s*\(/i', $query, $matches, PREG_OFFSET_CAPTURE)) {
                    $table = trim($matches[1][0], '`');
                    $colsStr = $matches[2][0];
                    $cols = array_map(function($c) { return trim($c, ' `'); }, explode(',', $colsStr));
                    
                    // Start parsing values after "VALUES ("
                    $startOffset = $matches[0][1] + strlen($matches[0][0]);
                    $valuesStr = '';
                    $len = strlen($query);
                    $parenDepth = 0;
                    $inQuote = false;
                    $quoteChar = '';
                    $escaped = false;
                    
                    for ($i = $startOffset; $i < $len; $i++) {
                        $char = $query[$i];
                        
                        if ($escaped) {
                            $escaped = false;
                            $valuesStr .= $char;
                            continue;
                        }
                        
                        if ($char === '\\') {
                            $escaped = true;
                            $valuesStr .= $char;
                            continue;
                        }
                        
                        if ($inQuote) {
                            if ($char === $quoteChar) {
                                $inQuote = false;
                            }
                            $valuesStr .= $char;
                        } else {
                            if ($char === "'" || $char === '"') {
                                $inQuote = true;
                                $quoteChar = $char;
                                $valuesStr .= $char;
                            } elseif ($char === '(') {
                                $parenDepth++;
                                $valuesStr .= $char;
                            } elseif ($char === ')') {
                                if ($parenDepth === 0) {
                                    // End of VALUES list
                                    break; 
                                }
                                $parenDepth--;
                                $valuesStr .= $char;
                            } else {
                                $valuesStr .= $char;
                            }
                        }
                    }
                    
                    // Now split $valuesStr by comma, respecting quotes
                    $vals = [];
                    $buffer = '';
                    $inQuote = false;
                    $quoteChar = '';
                    $escaped = false;
                    
                    for ($i = 0; $i < strlen($valuesStr); $i++) {
                        $char = $valuesStr[$i];
                        if ($escaped) {
                            $buffer .= $char;
                            $escaped = false;
                            continue;
                        }
                        if ($char === '\\') {
                            $buffer .= $char;
                            $escaped = true;
                            continue;
                        }
                        if ($inQuote) {
                            if ($char === $quoteChar) {
                                $inQuote = false;
                            }
                            $buffer .= $char;
                        } else {
                            if ($char === "'" || $char === '"') {
                                $inQuote = true;
                                $quoteChar = $char;
                                $buffer .= $char;
                            } elseif ($char === ',') {
                                $vals[] = trim($buffer);
                                $buffer = '';
                            } else {
                                $buffer .= $char;
                            }
                        }
                    }
                    $vals[] = trim($buffer);

                    // Clean values
                    $vals = array_map(function($v) {
                        // Remove quotes
                        if ((strpos($v, "'") === 0 && strrpos($v, "'") === strlen($v)-1) || 
                            (strpos($v, '"') === 0 && strrpos($v, '"') === strlen($v)-1)) {
                            return stripcslashes(substr($v, 1, -1));
                        }
                        if (strcasecmp($v, 'NULL') === 0) return null;
                        if (is_numeric($v)) return $v + 0;
                        return $v;
                    }, $vals);
                    
                    if (count($cols) === count($vals)) {
                        $data = array_combine($cols, $vals);
                        $this->insert($table, $data);
                        return 1;
                    }
                }
                return 1;
            }

            // Handle UPDATE
            if (stripos($query, 'UPDATE') === 0) {
                if (preg_match('/UPDATE\s+([^\s]+)\s+SET\s+(.+?)\s+WHERE\s+(.+?)(?:\s+LIMIT|$)/is', $query, $matches)) {
                    $table = trim($matches[1], '`');
                    $setClause = $matches[2];
                    $whereClause = $matches[3];

                    if (!isset($this->table_data[$table])) {
                        return 0;
                    }

                    // Pre-process NOW()
                    $setClause = str_ireplace('NOW()', "'" . date('Y-m-d H:i:s') . "'", $setClause);
                    
                    // Parse SET clause
                    $updates = [];
                    if (preg_match_all('/(\w+)\s*=\s*(?:[\'"]([^\'"]*)[\'"]|(\d+))/', $setClause, $setMatches, PREG_SET_ORDER)) {
                        foreach ($setMatches as $sm) {
                             $col = $sm[1];
                             $val = !empty($sm[3]) ? $sm[3] : $sm[2];
                             $updates[$col] = $val;
                        }
                    }

                    $affected = 0;
                    foreach ($this->table_data[$table] as $i => $row) {
                        if ($this->evaluateWhere($row, $whereClause)) {
                            $this->table_data[$table][$i] = array_merge($row, $updates);
                            $affected++;
                        }
                    }
                    return $affected;
                }
                return 1; // Fallback
            }

            // Handle DELETE
            if (stripos($query, 'DELETE FROM') === 0) {
                if (preg_match('/DELETE\s+FROM\s+([^\s]+)\s+WHERE\s+(.+?)(?:\s+LIMIT|$)/i', $query, $matches)) {
                    $table = trim($matches[1], '`');
                    $whereClause = $matches[2];

                    if (!isset($this->table_data[$table])) {
                        return 0;
                    }

                    $initialCount = count($this->table_data[$table]);
                    $this->table_data[$table] = array_filter($this->table_data[$table], function($row) use ($whereClause) {
                        return !$this->evaluateWhere($row, $whereClause);
                    });
                    
                    return $initialCount - count($this->table_data[$table]);
                }
                return 1; // Fallback
            }

            // Handle START TRANSACTION / COMMIT / ROLLBACK
            if (stripos($query, 'START TRANSACTION') === 0) {
                $this->transactionStatus[] = 'START TRANSACTION';
                return true;
            }
            if (stripos($query, 'COMMIT') === 0) {
                $this->transactionStatus[] = 'COMMIT';
                return true;
            }
            if (stripos($query, 'ROLLBACK') === 0) {
                $this->transactionStatus[] = 'ROLLBACK';
                return true;
            }

            return true;
        }

        public function insert($table, $data, $format = null)
        {
            if (isset($data['id']) && is_numeric($data['id'])) {
                $this->insert_id = $data['id'];
            } elseif (!isset($data['id'])) {
                $this->insert_id++;
                $data['id'] = $this->insert_id;
            }
            // If data['id'] is set but not numeric (e.g. UUID), we do NOT update insert_id,
            // and we leave data['id'] as is.

            if (!isset($this->table_data[$table])) {
                $this->table_data[$table] = [];
            }
            $this->table_data[$table][] = $data;

            // Update debug counters
            if (!isset($this->insertCountByTable[$table])) {
                $this->insertCountByTable[$table] = 0;
            }
            $this->insertCountByTable[$table]++;
            $this->lastInsertIdByTable[$table] = $data['id'] ?? 'unknown';

            return 1;
        }

        public function update($table, $data, $where, $format = null, $where_format = null)
        {
            if (!isset($this->table_data[$table])) {
                // Table not found
                return 0;
            }

            $updated = 0;
            foreach ($this->table_data[$table] as $i => $row) {
                $match = true;
                foreach ($where as $key => $value) {
                    if (!isset($row[$key]) || $row[$key] != $value) {
                        $match = false;
                        break;
                    }
                }

                if ($match) {
                    $this->table_data[$table][$i] = array_merge($row, $data);
                    $updated++;
                }
            }
            if ($updated === 0) {
                 // No rows matched
            }
            return $updated;
        }

        public function delete($table, $where, $where_format = null)
        {
            if (!isset($this->table_data[$table])) {
                return 0;
            }

            $initialCount = count($this->table_data[$table]);
            $this->table_data[$table] = array_filter($this->table_data[$table], function ($row) use ($where) {
                foreach ($where as $key => $value) {
                    if (isset($row[$key]) && $row[$key] == $value) {
                        return false; // Remove
                    }
                }
                return true; // Keep
            });

            return $initialCount - count($this->table_data[$table]);
        }

        public function replace($table, $data, $format = null)
        {
            $this->last_query = "REPLACE INTO $table ...";
            
            // rudimentary primary key detection for known tables to simulate REPLACE behavior
            $pk = [];
            if (strpos($table, 'pet_conversation_read_state') !== false) {
                $pk = ['conversation_id', 'user_id'];
            } elseif (strpos($table, 'pet_conversation_participants') !== false) {
                if (isset($data['user_id'])) $pk = ['conversation_id', 'user_id'];
                elseif (isset($data['contact_id'])) $pk = ['conversation_id', 'contact_id'];
                elseif (isset($data['team_id'])) $pk = ['conversation_id', 'team_id'];
            }
            
            if (!empty($pk)) {
                // Delete existing rows matching the PK
                if (!isset($this->table_data[$table])) {
                     $this->table_data[$table] = [];
                }
                foreach ($this->table_data[$table] as $i => $row) {
                    $match = true;
                    foreach ($pk as $k) {
                        // Loose comparison to handle string/int differences
                        if (!isset($row[$k]) || $row[$k] != $data[$k]) {
                            $match = false;
                            break;
                        }
                    }
                    if ($match) {
                        unset($this->table_data[$table][$i]);
                    }
                }
                // Re-index array
                $this->table_data[$table] = array_values($this->table_data[$table]);
            }
            
            // Insert new row
            if (!isset($this->table_data[$table])) {
                $this->table_data[$table] = [];
            }
            $this->table_data[$table][] = $data;
            
            return 1;
        }

        public function prepare($query, ...$args)
        {
            if (is_null($query)) {
                return;
            }

            // Simple mock of prepare: replace %s and %d with values
            $query = str_replace('%s', "'%s'", $query); // Quote strings
            $query = str_replace('%d', "%d", $query);   // Integers
            $query = str_replace('%f', "%f", $query);   // Floats

            if (isset($args[0]) && is_array($args[0]) && count($args) === 1) {
                $args = $args[0];
            }
            
            // Escape strings to avoid breaking SQL syntax in our simple parser
            $args = array_map(function($a) {
                return is_string($a) ? addslashes($a) : $a;
            }, $args);

            return vsprintf($query, $args);
        }

        public function esc_like($text)
        {
            return addcslashes($text, '_%\\');
        }

        public function get_results($query = null, $output = OBJECT)
        {
            $this->last_query = $query;
            $this->query_log[] = $query;
            
            // Handle SHOW TABLES LIKE 'table'
            if (preg_match('/SHOW\s+TABLES\s+LIKE\s+[\'"](.+?)[\'"]/i', $query, $matches)) {
                $tableName = $matches[1];
                if (isset($this->table_data[$tableName])) {
                     $res = [$tableName => $tableName]; 
                     if ($output == OBJECT) {
                         return [(object)$res];
                     }
                     return [$res];
                }
                return [];
            }

            // Handle DESCRIBE
            if (preg_match('/DESCRIBE\s+([^\s]+)/i', $query, $matches)) {
                $table = trim($matches[1], "`'\"");
                $cols = [];
                if (isset($this->table_schema[$table])) {
                    $cols = $this->table_schema[$table];
                } elseif (isset($this->table_data[$table])) {
                    foreach ($this->table_data[$table] as $row) {
                        $cols = array_merge($cols, array_keys($row));
                    }
                    $cols = array_unique($cols);
                }
                
                $results = [];
                foreach ($cols as $col) {
                    $results[] = ['Field' => $col, 'Type' => 'varchar(255)', 'Null' => 'YES', 'Key' => '', 'Default' => null, 'Extra' => ''];
                }
                
                if ($output == OBJECT) {
                     return array_map(function($r) { return (object)$r; }, $results);
                }
                return $results;
            }

            // Handle SELECT COUNT(*)
            if (preg_match('/SELECT\s+COUNT\(\*\)\s+FROM\s+([^\s]+)(?:\s+WHERE\s+(.+?))?(?:\s+LIMIT\s+\d+)?$/i', $query, $matches)) {
                $table = trim($matches[1], "`'\"");
                $whereClause = isset($matches[2]) ? $matches[2] : '';
                
                if (!isset($this->table_data[$table])) {
                    return [0];
                }

                $rows = array_filter($this->table_data[$table], function($row) use ($whereClause) {
                    return $this->evaluateWhere($row, $whereClause);
                });

                return [count($rows)];
            }

            // Handle SELECT *
            if (preg_match('/SELECT\s+\*\s+FROM\s+([^\s]+)\s+WHERE\s+(.+?)(?:\s+ORDER\s+BY.+?)?(?:\s+LIMIT\s+\d+)?$/i', $query, $matches)) {
                $table = trim($matches[1], "`'\"");
                $whereClause = $matches[2];

                if (!isset($this->table_data[$table])) {
                    return [];
                }

                $rows = array_filter($this->table_data[$table], function($row) use ($whereClause) {
                    return $this->evaluateWhere($row, $whereClause);
                });
                
                // Reset keys
                $rows = array_values($rows);

                if ($output == OBJECT) {
                    return array_map(function($r) { return (object)$r; }, $rows);
                }
                return $rows;
            }

            // Check for SELECT * FROM table WHERE ...
            if (preg_match('/FROM\s+([^\s]+)/i', $query, $matches)) {
                $table = trim($matches[1], "`'\"");
                if (isset($this->table_data[$table])) {
                    $results = $this->table_data[$table];
                    // Filter based on WHERE clause
                    if (preg_match('/WHERE\s+(.+?)(?:\s+ORDER|\s+LIMIT|$)/is', $query, $whereMatches)) {
                        $whereClause = $whereMatches[1];
                        
                        $results = array_filter($results, function($r) use ($whereClause) {
                            return $this->evaluateWhere($r, $whereClause);
                        });
                    }
                    
                    // Convert to object if requested
                    if ($output == OBJECT) {
                        return array_map(function($r) { return (object)$r; }, array_values($results));
                    }
                    return array_values($results);
                }
            }
            return [];
        }

        public function get_var($query = null, $x = 0, $y = 0)
        {
            $this->last_query = $query;
            $this->query_log[] = $query;
            $row = $this->get_row($query, ARRAY_A, $y);
            if ($row) {
                // If the row is a simple array from get_results for COUNT(*), it might be [0 => count] or ['COUNT(*)' => count]
                // If it came from get_row's special handling, it is ['COUNT(*)' => val]
                
                // Try to determine which column was requested
                if (preg_match('/SELECT\s+([^\s,]+)\s+FROM/i', $query, $matches)) {
                    $col = trim($matches[1], '`');
                    if (strpos($col, '.') !== false) {
                        $parts = explode('.', $col);
                        $col = end($parts);
                    }
                    if ($col !== '*') {
                        if (is_numeric($col)) {
                            return (int)$col;
                        }
                        if (array_key_exists($col, $row)) return $row[$col];
                    }
                }
                // Fallback to first column
                return reset($row);
            }
            return null;
        }

        public function get_row($query = null, $output = OBJECT, $y = 0)
        {
            $this->last_query = $query;
            $this->query_log[] = $query;

            // Remove FOR UPDATE / LOCK IN SHARE MODE for in-memory stub
            $query = preg_replace('/\s+FOR\s+UPDATE\s*$/i', '', $query);
            $query = preg_replace('/\s+LOCK\s+IN\s+SHARE\s+MODE\s*$/i', '', $query);
            
            // Handle COUNT(*) aggregation
            if (preg_match('/SELECT\s+COUNT\(\*\)(?:\s+AS\s+(\w+))?\s+FROM/i', $query, $matches)) {
                $alias = !empty($matches[1]) ? $matches[1] : 'COUNT(*)';
                $results = $this->get_results($query, ARRAY_A);
                // get_results returns [count] for COUNT(*)
                $val = !empty($results) ? $results[0] : 0;
                $result = [$alias => $val];
                return $output == OBJECT ? (object)$result : $result;
            }
            
            // Handle MAX aggregation
            if (preg_match('/SELECT\s+MAX\((.+?)\)\s+AS\s+(\w+)\s+FROM/i', $query, $matches)) {
                $col = trim($matches[1]);
                $alias = $matches[2];
                
                // Get filtered results (all of them)
                $results = $this->get_results($query, ARRAY_A);
                
                if (empty($results)) {
                    // If no rows, MAX is NULL
                    $result = [$alias => null];
                } else {
                    $max = null;
                    foreach ($results as $row) {
                        if (isset($row[$col])) {
                            if ($max === null || $row[$col] > $max) {
                                $max = $row[$col];
                            }
                        }
                    }
                    $result = [$alias => $max];
                }
                
                return $output == OBJECT ? (object)$result : $result;
            }

            // Reuse get_results logic
            $results = $this->get_results($query, ARRAY_A);
            if (!empty($results)) {
                $row = $results[0];
                return $output == OBJECT ? (object)$row : $row;
            }
            return null;
        }

        public function get_col($query = null, $x = 0)
        {
            $this->last_query = $query;
            $this->query_log[] = $query;
            $results = $this->get_results($query, ARRAY_A);
            
            // Try to identify column name
            if (preg_match('/SELECT\s+([^\s,]+)\s+FROM/i', $query, $matches)) {
                $col = trim($matches[1], '`');
                if ($col !== '*') {
                    return array_map(function($r) use ($col) {
                        return array_key_exists($col, $r) ? $r[$col] : null;
                    }, $results);
                }
            }

            return array_map(function($r) { return reset($r); }, $results);
        }

    private function evaluateWhere($row, $whereClause) {
        $whereClause = trim($whereClause);
        if (empty($whereClause)) return true;

        // Normalize spaces
        $whereClause = preg_replace('/\s+/', ' ', $whereClause);

        // 1. Handle "AND" split (Top level)
        $parts = [];
        $buffer = '';
        $parenDepth = 0;
        $len = strlen($whereClause);
        
        for ($i = 0; $i < $len; $i++) {
            $char = $whereClause[$i];
            if ($char === '(') $parenDepth++;
            if ($char === ')') $parenDepth--;
            
            // Split on AND at top-level, case-insensitive
            if ($parenDepth === 0 && strncasecmp(substr($whereClause, $i, 5), ' AND ', 5) === 0) {
                $parts[] = trim($buffer);
                $buffer = '';
                $i += 4; // Skip " AND"
                continue;
            }
            $buffer .= $char;
        }
        $parts[] = trim($buffer);
        
        foreach ($parts as $part) {
            // Remove outer parens if present
            while (str_starts_with($part, '(') && str_ends_with($part, ')') && $this->isBalanced(substr($part, 1, -1))) {
                $part = trim(substr($part, 1, -1));
            }

            // Handle nested AND groups only when a top-level AND exists
            $andParts = $this->splitByKeyword($part, 'AND');
            if (count($andParts) > 1) {
                foreach ($andParts as $andPart) {
                    if (!$this->evaluateWhere($row, $andPart)) {
                        return false;
                    }
                }
                continue;
            }

            if (!$this->evaluateCondition($row, $part)) {
                return false;
            }
        }
        
        return true;
    }
    
    private function isBalanced($str) {
        $depth = 0;
        for ($i = 0; $i < strlen($str); $i++) {
            if ($str[$i] === '(') $depth++;
            if ($str[$i] === ')') $depth--;
            if ($depth < 0) return false;
        }
        return $depth === 0;
    }

    private function evaluateCondition($row, $condition) {
        $condition = trim($condition);
        
        // Handle OR
        $orParts = $this->splitByKeyword($condition, 'OR');
        if (count($orParts) > 1) {
            foreach ($orParts as $part) {
                if ($this->evaluateCondition($row, $part)) return true;
            }
            return false;
        }

        // Handle NOT (...)
        if (preg_match('/^NOT\s+\((.+)\)$/i', $condition, $m) && $this->isBalanced($m[1])) {
            return !$this->evaluateCondition($row, $m[1]);
        }

        // Handle specific operators
        
        // 1. IS NULL
        if (preg_match('/(.+?)\s+IS\s+NULL$/i', $condition, $m)) {
            $val = $this->extractValue($row, $m[1]);
            return $val === null;
        }
        
        // 2. IS NOT NULL
        if (preg_match('/(.+?)\s+IS\s+NOT\s+NULL$/i', $condition, $m)) {
            $val = $this->extractValue($row, $m[1]);
            return $val !== null;
        }
        
        // 3. NOT IN (...)
        if (preg_match('/(.+?)\s+NOT\s+IN\s*\((.+)\)$/i', $condition, $m)) {
            $val = $this->extractValue($row, $m[1]);
            $options = $this->parseList($m[2]);
            return !in_array($val, $options);
        }

        // 4. IN (...)
        if (preg_match('/(.+?)\s+IN\s*\((.+)\)$/i', $condition, $m)) {
            $val = $this->extractValue($row, $m[1]);
            $options = $this->parseList($m[2]);
            // DEBUG
            // error_log("InMemoryWpdb IN check: Val=" . var_export($val, true) . " Options=" . json_encode($options) . " Result=" . (in_array($val, $options) ? 'TRUE' : 'FALSE'));
            return in_array($val, $options);
        }

        // 5. >=
        if (preg_match('/(.+?)\s*>=\s*(.+)$/', $condition, $m)) {
            $val1 = $this->extractValue($row, $m[1]);
            $val2 = $this->parseValue($m[2]);
            if ($val1 === null || $val2 === null) return false;
            return $val1 >= $val2;
        }

        // 6. <=
        if (preg_match('/(.+?)\s*<=\s*(.+)$/', $condition, $m)) {
            $val1 = $this->extractValue($row, $m[1]);
            $val2 = $this->parseValue($m[2]);
            if ($val1 === null || $val2 === null) return false;
            
            return $val1 <= $val2;
        }
        
        // 7. != or <>
        if (preg_match('/(.+?)\s*(?:!=|<>)\s*(.+)$/', $condition, $m)) {
            $val1 = $this->extractValue($row, $m[1]);
            $val2 = $this->parseValue($m[2]);
            if ($val1 === null || $val2 === null) {
                return false;
            }
            return $val1 != $val2;
        }

        // 8. =
        if (preg_match('/(.+?)\s*=\s*(.+)$/', $condition, $m)) {
            $val1 = $this->extractValue($row, $m[1]);
            $val2 = $this->parseValue($m[2]);
            // strict equality for nulls?
            if ($val1 === null && $val2 === null) return true;
            if ($val1 === null || $val2 === null) return false;
            return $val1 == $val2;
        }

        // 9. >
        if (preg_match('/(.+?)\s*>\s*(.+)$/', $condition, $m)) {
            $val1 = $this->extractValue($row, $m[1]);
            $rhs = trim($m[2]);
            if (preg_match('/^[\'"]/', $rhs) || is_numeric($rhs) || strcasecmp($rhs, 'NULL') === 0) {
                $val2 = $this->parseValue($rhs);
            } else {
                $val2 = $this->extractValue($row, $rhs);
            }
            if ($val1 === null || $val2 === null) {
                return false;
            }
            return $val1 > $val2;
        }

        // 10. <
        if (preg_match('/(.+?)\s*<\s*(.+)$/', $condition, $m)) {
            $val1 = $this->extractValue($row, $m[1]);
            $rhs = trim($m[2]);
            if (preg_match('/^[\'"]/', $rhs) || is_numeric($rhs) || strcasecmp($rhs, 'NULL') === 0) {
                $val2 = $this->parseValue($rhs);
            } else {
                $val2 = $this->extractValue($row, $rhs);
            }
            if ($val1 === null || $val2 === null) {
                return false;
            }
            return $val1 < $val2;
        }
        
        // 11. LIKE
        if (preg_match('/(.+?)\s+LIKE\s+(.+)$/i', $condition, $m)) {
            $val = $this->extractValue($row, $m[1]);
            $pattern = $this->parseValue($m[2]);
            if ($val === null || $pattern === null) return false;
            
            // Simple LIKE implementation
            $p = preg_quote($pattern, '/');
            $p = str_replace(['%', '_'], ['.*', '.'], $p);
            return (bool)preg_match('/^' . $p . '$/i', $val);
        }
        
        return false;
    }

    private function splitByKeyword($str, $keyword) {
        $parts = [];
        $buffer = '';
        $parenDepth = 0;
        $len = strlen($str);
        for ($i = 0; $i < $len; $i++) {
            $char = $str[$i];
            if ($char === '(') $parenDepth++;
            if ($char === ')') $parenDepth--;
            if ($parenDepth === 0) {
                $slice = substr($str, $i);
                if (preg_match('/^\s+' . preg_quote($keyword, '/') . '\s+/i', $slice, $m)) {
                    $parts[] = trim($buffer);
                    $buffer = '';
                    $i += strlen($m[0]) - 1;
                    continue;
                }
            }
            $buffer .= $char;
        }
        $parts[] = trim($buffer);
        return $parts;
    }

    private function extractValue($row, $expr) {
        $expr = trim($expr);
        // Check for JSON_EXTRACT
        if (preg_match('/JSON_EXTRACT\s*\(\s*([a-zA-Z0-9_\.]+)\s*,\s*[\'"]\$\.([^"\']+)[\'"]\s*\)/i', $expr, $m)) {
            $col = $m[1];
            if (strpos($col, '.') !== false) {
                $parts = explode('.', $col);
                $col = end($parts);
            }
            $key = $m[2];
            $json = $row[$col] ?? '{}';
            $data = json_decode($json, true);
            // Handle nested keys? Simple implementation for now
            return $data[$key] ?? null;
        }
        
        // Check for column name
        // Remove potential table alias or quotes
        $expr = trim($expr, '`');
        if (strpos($expr, '.') !== false) {
            $parts = explode('.', $expr);
            $expr = end($parts);
        }
        
        if (array_key_exists($expr, $row)) {
            return $row[$expr];
        }
        
        return null; 
    }

    private function parseValue($val) {
        $val = trim($val);
        if ((str_starts_with($val, "'") && str_ends_with($val, "'")) || 
            (str_starts_with($val, '"') && str_ends_with($val, '"'))) {
            return substr($val, 1, -1);
        }
        if (is_numeric($val)) return $val + 0;
        if (strcasecmp($val, 'NULL') === 0) return null;
        return $val;
    }
    
    private function parseList($listStr) {
        // Use a simpler parsing method to avoid potential str_getcsv issues
        $listStr = trim($listStr);
        if (empty($listStr)) return [];
        
        $parts = explode(',', $listStr);
        return array_map(function($v) { 
            return trim(trim($v), " '\""); 
        }, $parts);
    }
    }
}

if (!defined('ARRAY_A')) define('ARRAY_A', 'ARRAY_A');
if (!defined('ARRAY_N')) define('ARRAY_N', 'ARRAY_N');
if (!defined('OBJECT')) define('OBJECT', 'OBJECT');
if (!defined('OBJECT_K')) define('OBJECT_K', 'OBJECT_K');
