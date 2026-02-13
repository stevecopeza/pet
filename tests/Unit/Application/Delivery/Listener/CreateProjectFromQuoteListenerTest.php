<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Application\Delivery\Listener;

use PHPUnit\Framework\TestCase;
use Pet\Application\Delivery\Listener\CreateProjectFromQuoteListener;
use Pet\Application\Delivery\Command\CreateProjectHandler;
use Pet\Application\Delivery\Command\CreateProjectCommand;
use Pet\Domain\Commercial\Event\QuoteAccepted;
use Pet\Domain\Commercial\Entity\Quote;
use Pet\Domain\Commercial\Entity\Component\ImplementationComponent;
use Pet\Domain\Commercial\Entity\Component\QuoteMilestone;
use Pet\Domain\Commercial\Entity\Component\QuoteTask;
use Pet\Domain\Commercial\Entity\Component\CatalogComponent;

class CreateProjectFromQuoteListenerTest extends TestCase
{
    public function testCreatesProjectFromImplementationComponents()
    {
        // Setup Quote with Implementation Component
        $task = new QuoteTask('Task 1', 10.0, 1, 50.0, 100.0);
        $milestone = new QuoteMilestone('Milestone 1', [$task]);
        $implComponent = new ImplementationComponent([$milestone], 'Impl 1');
        
        $catalogComponent = new CatalogComponent([], 'Catalog 1'); 
        
        $quote = $this->createMock(Quote::class);
        $quote->method('components')->willReturn([$implComponent, $catalogComponent]);
        $quote->method('id')->willReturn(123);
        $quote->method('customerId')->willReturn(1);
        
        $event = new QuoteAccepted($quote);
        
        // Mock Handler
        $handler = $this->createMock(CreateProjectHandler::class);
        $handler->expects($this->once())
            ->method('handle')
            ->with($this->callback(function (CreateProjectCommand $command) {
                return $command->soldHours() === 10.0 
                    && $command->soldValue() === 1000.0 // 10 * 100
                    && $command->sourceQuoteId() === 123;
            }));
            
        $listener = new CreateProjectFromQuoteListener($handler);
        $listener($event);
    }
    
    public function testDoesNotCreateProjectIfNoImplementation()
    {
        $quote = $this->createMock(Quote::class);
        $quote->method('components')->willReturn([]);
        
        $event = new QuoteAccepted($quote);
        
        $handler = $this->createMock(CreateProjectHandler::class);
        $handler->expects($this->never())->method('handle');
            
        $listener = new CreateProjectFromQuoteListener($handler);
        $listener($event);
    }
}
