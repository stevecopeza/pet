<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CreateRoleHandlerReturnsIntTest extends TestCase
{
    private \DI\Container $container;

    protected function setUp(): void
    {
        global $wpdb;
        $wpdb = new \Pet\Tests\Stubs\InMemoryWpdb();
        $wpdb->table_data[$wpdb->prefix . 'pet_roles'] = [];
        $wpdb->table_data[$wpdb->prefix . 'pet_role_skills'] = [];
        
        \Pet\Infrastructure\DependencyInjection\ContainerFactory::reset();
        $this->container = \Pet\Infrastructure\DependencyInjection\ContainerFactory::create();
    }

    public function testHandleReturnsIntId(): void
    {
        $handler = $this->container->get(\Pet\Application\Work\Command\CreateRoleHandler::class);
        $cmd = new \Pet\Application\Work\Command\CreateRoleCommand(
            'Integration Role ' . uniqid(),
            'senior',
            'Role created by integration test',
            'Success criteria example',
            []
        );
        $id = $handler->handle($cmd);
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }
}
