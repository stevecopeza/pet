<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Infrastructure\DependencyInjection;

use Pet\Domain\Event\EventBus;
use Pet\Infrastructure\DependencyInjection\ContainerFactory;
use Pet\Infrastructure\Event\InMemoryEventBus;
use Pet\Infrastructure\Event\PersistingEventBus;
use PHPUnit\Framework\TestCase;

class ContainerFactoryTest extends TestCase
{
    public function testContainerCreation(): void
    {
        $GLOBALS['wpdb'] = new \wpdb('user', 'pass', 'db', 'host');
        $container = ContainerFactory::create();
        
        $this->assertTrue($container->has(EventBus::class));
        $this->assertInstanceOf(PersistingEventBus::class, $container->get(EventBus::class));
    }
}
