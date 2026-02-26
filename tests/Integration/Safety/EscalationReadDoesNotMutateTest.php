<?php

declare(strict_types=1);

namespace Pet\Tests\Integration\Safety;

use PHPUnit\Framework\TestCase;
use Pet\UI\Rest\Controller\EscalationRuleController;
use Pet\Infrastructure\Persistence\Repository\SqlEscalationRuleRepository;
use Pet\Application\System\Service\FeatureFlagService;
use WP_REST_Request;

class EscalationReadDoesNotMutateTest extends TestCase
{
    private $wpdb;

    protected function setUp(): void
    {
        $this->wpdb = $this->createMock(\wpdb::class);
        $this->wpdb->prefix = 'wp_';
        
        $this->wpdb->method('prepare')->willReturnCallback(function ($query, ...$args) {
            $query = str_replace('%d', '%s', $query);
            $query = str_replace('%s', '%s', $query);
            return vsprintf($query, $args);
        });

        // STRICT SAFETY CHECK: No mutations allowed on read
        $this->wpdb->expects($this->never())->method('insert');
        $this->wpdb->expects($this->never())->method('update');
        $this->wpdb->expects($this->never())->method('delete');
        $this->wpdb->expects($this->never())->method('replace');
    }

    public function testGetRulesDoesNotMutateState()
    {
        // 1. Instantiate Controller
        $repo = new SqlEscalationRuleRepository($this->wpdb);
        $featureFlags = $this->createMock(FeatureFlagService::class);
        $featureFlags->method('isEscalationEngineEnabled')->willReturn(true);

        $controller = new EscalationRuleController($repo, $featureFlags);

        // 2. Mock DB to return no rules (or some rules, doesn't matter, read only)
        // findAll calls get_results
        $this->wpdb->method('get_results')->willReturn([]);

        // 3. Call Endpoint
        $request = new WP_REST_Request('GET', '/pet/v1/escalation-rules');
        $response = $controller->getRules($request);

        // 4. Assert
        $this->assertEquals(200, $response->get_status());
        // Implicitly asserts no DB writes via setUp() expectations
    }
}
