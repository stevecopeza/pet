<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Infrastructure\DependencyInjection;

use Pet\Domain\Event\EventBus;
use Pet\Infrastructure\DependencyInjection\ContainerFactory;
use Pet\Infrastructure\Event\InMemoryEventBus;
use PHPUnit\Framework\TestCase;

class ContainerFactoryTest extends TestCase
{
    public function testContainerCreation(): void
    {
        $container = ContainerFactory::create();
        
        $this->assertTrue($container->has(EventBus::class));
        $this->assertInstanceOf(InMemoryEventBus::class, $container->get(EventBus::class));
    }
}
