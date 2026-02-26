<?php

declare(strict_types=1);

namespace Pet\Tests\Integration\Infrastructure\Persistence\Repository;

use Pet\Domain\Sla\Entity\EscalationRule;
use Pet\Infrastructure\Persistence\Repository\SqlEscalationRuleRepository;
use PHPUnit\Framework\TestCase;

class SqlEscalationRuleRepositoryTest extends TestCase
{
    private $wpdb;
    private $repository;

    protected function setUp(): void
    {
        $this->wpdb = $this->createMock(\wpdb::class);
        $this->wpdb->prefix = 'wp_';
        $this->repository = new SqlEscalationRuleRepository($this->wpdb);
    }

    public function testSaveInsertNewRule(): void
    {
        $rule = new EscalationRule(80, 'notify_manager', null, '{"priority":"high"}', true);
        $slaId = 123;

        $this->wpdb->expects($this->once())
            ->method('insert')
            ->with(
                'wp_pet_sla_escalation_rules',
                [
                    'threshold_percent' => 80,
                    'action' => 'notify_manager',
                    'criteria_json' => '{"priority":"high"}',
                    'is_enabled' => 1,
                    'sla_id' => 123
                ]
            );

        // We cannot easily mock the public property insert_id on a PHPUnit mock object reliably
        // without using a real object or partial mock, so we verify the insert call happens.
        
        $this->repository->save($rule, $slaId);
    }

    public function testSaveUpdateExistingRule(): void
    {
        $rule = new EscalationRule(90, 'notify_admin', 555, '{"priority":"critical"}', false);

        $this->wpdb->expects($this->once())
            ->method('update')
            ->with(
                'wp_pet_sla_escalation_rules',
                [
                    'threshold_percent' => 90,
                    'action' => 'notify_admin',
                    'criteria_json' => '{"priority":"critical"}',
                    'is_enabled' => 0
                ],
                ['id' => 555]
            );

        $this->repository->save($rule);
    }

    public function testFindByIdReturnsRule(): void
    {
        $row = (object) [
            'id' => '10',
            'threshold_percent' => '75',
            'action' => 'email',
            'criteria_json' => '{"foo":"bar"}',
            'is_enabled' => '1'
        ];

        $this->wpdb->method('prepare')->willReturn('SQL');
        $this->wpdb->expects($this->once())
            ->method('get_row')
            ->willReturn($row);

        $rule = $this->repository->findById(10);

        $this->assertNotNull($rule);
        $this->assertSame(10, $rule->id());
        $this->assertSame(75, $rule->thresholdPercent());
        $this->assertSame('email', $rule->action());
        $this->assertSame('{"foo":"bar"}', $rule->criteriaJson());
        $this->assertTrue($rule->isEnabled());
    }

    public function testEnableUpdatesStatus(): void
    {
        $this->wpdb->expects($this->once())
            ->method('update')
            ->with(
                'wp_pet_sla_escalation_rules',
                ['is_enabled' => 1],
                ['id' => 42]
            );

        $this->repository->enable(42);
    }

    public function testDisableUpdatesStatus(): void
    {
        $this->wpdb->expects($this->once())
            ->method('update')
            ->with(
                'wp_pet_sla_escalation_rules',
                ['is_enabled' => 0],
                ['id' => 42]
            );

        $this->repository->disable(42);
    }
}
