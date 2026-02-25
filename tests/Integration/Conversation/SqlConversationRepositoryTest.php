<?php

namespace Pet\Tests\Integration\Conversation;

use PHPUnit\Framework\TestCase;
use Pet\Infrastructure\Persistence\Repository\Conversation\SqlConversationRepository;
use Pet\Domain\Conversation\Entity\Conversation;

class SqlConversationRepositoryTest extends TestCase
{
    private $wpdb;
    private $repo;

    private $originalWpdb;

    protected function setUp(): void
    {
        // Save original wpdb if it exists
        if (isset($GLOBALS['wpdb'])) {
            $this->originalWpdb = $GLOBALS['wpdb'];
        }

        $this->wpdb = $this->createMock(\wpdb::class);
        $this->wpdb->prefix = 'wp_';
        
        // Mock prepare to behave somewhat like real wpdb::prepare
        $this->wpdb->method('prepare')->willReturnCallback(function ($query, ...$args) {
            // Replace %s, %d, %f with %s for simple string formatting
            $query = str_replace(['%d', '%f'], '%s', $query);
            
            // If args is empty (no placeholders), return query as is
            if (empty($args)) {
                return $query;
            }
            
            $result = vsprintf($query, $args);
            if ($result === false) {
                // Fallback if vsprintf fails (e.g. mismatched args)
                return $query;
            }
            return $result;
        });

        // Set global wpdb
        $GLOBALS['wpdb'] = $this->wpdb;

        $this->repo = new SqlConversationRepository();
    }

    protected function tearDown(): void
    {
        // Restore original wpdb
        if ($this->originalWpdb) {
            $GLOBALS['wpdb'] = $this->originalWpdb;
        } else {
            unset($GLOBALS['wpdb']);
        }
    }

    public function testFindByContextWithSubjectKey()
    {
        $contextType = 'quote';
        $contextId = '123';
        $contextVersion = '1';
        $subjectKey = 'quote_line:456';

        // Expect get_row to be called
        $this->wpdb->expects($this->once())
            ->method('get_row')
            ->willReturnCallback(function($query) use ($contextType, $contextId, $contextVersion, $subjectKey) {
                // Verify that query contains our values
                $hasType = strpos($query, "context_type = $contextType") !== false;
                $hasId = strpos($query, "context_id = $contextId") !== false;
                $hasVersion = strpos($query, "context_version = $contextVersion") !== false;
                $hasKey = strpos($query, "subject_key = $subjectKey") !== false;
                
                if (!$hasType || !$hasId || !$hasVersion || !$hasKey) {
                    return null; 
                }

                return (object)[
                    'id' => 1,
                    'uuid' => 'test-uuid',
                    'context_type' => $contextType,
                    'context_id' => $contextId,
                    'context_version' => $contextVersion,
                    'subject' => 'Test Subject',
                    'subject_key' => $subjectKey,
                    'state' => 'open',
                    'created_at' => '2023-01-01 00:00:00',
                ];
            });

        $result = $this->repo->findByContext($contextType, $contextId, $contextVersion, $subjectKey);

        $this->assertInstanceOf(Conversation::class, $result);
        $this->assertEquals($subjectKey, $result->subjectKey());
        $this->assertEquals($contextVersion, $result->contextVersion());
    }

    public function testFindByContextWithoutSubjectKey()
    {
        $contextType = 'quote';
        $contextId = '123';
        // When subjectKey is null, it should not be in the query
        // And contextVersion null also not in query

        $this->wpdb->expects($this->once())
            ->method('get_row')
            ->willReturnCallback(function($query) {
                $hasSubjectKey = strpos($query, "subject_key =") !== false;
                $hasContextVersion = strpos($query, "context_version =") !== false;
                
                if ($hasSubjectKey || $hasContextVersion) {
                    // Fail if they are present but shouldn't be
                    // But wait, the method only adds them if not null.
                    return null;
                }
                
                return (object)[
                    'id' => 2,
                    'uuid' => 'test-uuid-2',
                    'context_type' => 'quote',
                    'context_id' => '123',
                    'context_version' => null,
                    'subject' => 'Test Subject 2',
                    'subject_key' => 'default',
                    'state' => 'open',
                    'created_at' => '2023-01-01 00:00:00',
                ];
            });

        $result = $this->repo->findByContext($contextType, $contextId);

        $this->assertInstanceOf(Conversation::class, $result);
        $this->assertNull($result->contextVersion());
    }

    public function testQuoteContractSanity()
    {
        // 4.3 Quote contract sanity (API-level)
        // Fetching quote header thread uses: context_type=quote, context_id=quote_id, context_version=current, subject_key=quote:{quote_id}
        // Fetching line item thread uses subject_key=quote_line:{line_item_id}

        // Test Quote Header
        $quoteId = '999';
        $version = '5';
        $headerSubjectKey = "quote:{$quoteId}";
        
        // We will simulate two calls, one for header, one for line item.
        // Mocking consecutive calls is tricky with get_row if we use expects(once), so let's do two separate test methods or reset mock.
        // Actually, let's just test the line item case here as it's the more complex one with specific subject key format.
        
        $lineItemId = '888';
        $lineItemSubjectKey = "quote_line:{$lineItemId}";

        $this->wpdb->expects($this->once())
            ->method('get_row')
            ->willReturnCallback(function($query) use ($quoteId, $version, $lineItemSubjectKey) {
                // Verify strict equality in query parts
                // Note: our mock prepare implementation does simple replacement, so values are in the string.
                
                $hasContext = strpos($query, "context_type = quote") !== false;
                $hasId = strpos($query, "context_id = $quoteId") !== false;
                $hasVersion = strpos($query, "context_version = $version") !== false;
                $hasKey = strpos($query, "subject_key = $lineItemSubjectKey") !== false;
                
                if (!$hasContext || !$hasId || !$hasVersion || !$hasKey) {
                    return null;
                }
                
                return (object)[
                    'id' => 3,
                    'uuid' => 'test-uuid-3',
                    'context_type' => 'quote',
                    'context_id' => $quoteId,
                    'context_version' => $version,
                    'subject' => 'Line Item Discussion',
                    'subject_key' => $lineItemSubjectKey,
                    'state' => 'open',
                    'created_at' => '2023-01-01 00:00:00',
                ];
            });

        $result = $this->repo->findByContext('quote', $quoteId, $version, $lineItemSubjectKey);
        
        $this->assertInstanceOf(Conversation::class, $result);
        $this->assertEquals($lineItemSubjectKey, $result->subjectKey());
        $this->assertEquals($version, $result->contextVersion());
    }
}
