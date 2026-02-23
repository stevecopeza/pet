<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Application\Commercial\Listener;

use PHPUnit\Framework\TestCase;
use Pet\Application\Commercial\Listener\QuoteAcceptedListener;
use Pet\Domain\Commercial\Entity\Contract;
use Pet\Domain\Commercial\Entity\Baseline;
use Pet\Domain\Commercial\Entity\Quote;
use Pet\Domain\Commercial\Event\QuoteAccepted;
use Pet\Domain\Commercial\Event\ContractCreated;
use Pet\Domain\Commercial\Event\BaselineCreated;
use Pet\Domain\Commercial\Repository\ContractRepository;
use Pet\Domain\Commercial\Repository\BaselineRepository;
use Pet\Domain\Event\EventBus;

class QuoteAcceptedListenerTest extends TestCase
{
    public function testDispatchesContractAndBaselineEvents(): void
    {
        $quote = $this->createMock(Quote::class);
        $quote->method('id')->willReturn(123);
        $quote->method('customerId')->willReturn(10);
        $quote->method('totalValue')->willReturn(1000.0);
        $quote->method('totalInternalCost')->willReturn(600.0);
        $quote->method('currency')->willReturn('USD');
        $quote->method('acceptedAt')->willReturn(new \DateTimeImmutable('2024-01-01T00:00:00Z'));
        $quote->method('components')->willReturn([]);

        $event = new QuoteAccepted($quote);

        $contractRepo = $this->createMock(ContractRepository::class);
        $baselineRepo = $this->createMock(BaselineRepository::class);
        $eventBus = $this->createMock(EventBus::class);

        $contractRepo
            ->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Contract::class))
            ->willReturnCallback(function (Contract $contract): void {
                $reflection = new \ReflectionClass($contract);
                $property = $reflection->getProperty('id');
                $property->setAccessible(true);
                $property->setValue($contract, 999);
            });

        $baselineRepo
            ->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Baseline::class));

        $eventBus
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->withConsecutive(
                [$this->isInstanceOf(ContractCreated::class)],
                [$this->isInstanceOf(BaselineCreated::class)]
            );

        $listener = new QuoteAcceptedListener($contractRepo, $baselineRepo, $eventBus);
        $listener($event);
    }
}

