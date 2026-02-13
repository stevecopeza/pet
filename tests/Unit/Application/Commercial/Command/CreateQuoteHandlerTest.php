<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Application\Commercial\Command;

use PHPUnit\Framework\TestCase;
use Pet\Application\Commercial\Command\CreateQuoteCommand;
use Pet\Application\Commercial\Command\CreateQuoteHandler;
use Pet\Domain\Commercial\Entity\Quote;
use Pet\Domain\Commercial\Repository\QuoteRepository;
use Pet\Domain\Identity\Entity\Customer;
use Pet\Domain\Identity\Repository\CustomerRepository;

class CreateQuoteHandlerTest extends TestCase
{
    private $quoteRepository;
    private $customerRepository;
    private $handler;

    protected function setUp(): void
    {
        $this->quoteRepository = $this->createMock(QuoteRepository::class);
        $this->customerRepository = $this->createMock(CustomerRepository::class);
        $this->handler = new CreateQuoteHandler(
            $this->quoteRepository,
            $this->customerRepository
        );
    }

    public function testHandleCreatesQuoteSuccessfully()
    {
        $customerId = 1;
        $command = new CreateQuoteCommand($customerId, 'Test Quote', 'Description');
        
        $customer = $this->createMock(Customer::class);
        $this->customerRepository->method('findById')
            ->with($customerId)
            ->willReturn($customer);

        $this->quoteRepository->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Quote::class))
            ->will($this->returnCallback(function ($quote) {
                $ref = new \ReflectionClass($quote);
                $prop = $ref->getProperty('id');
                $prop->setAccessible(true);
                $prop->setValue($quote, 123);
            }));

        $this->handler->handle($command);
    }

    public function testHandleThrowsExceptionIfCustomerNotFound()
    {
        $customerId = 999;
        $command = new CreateQuoteCommand($customerId, 'Test Quote', 'Description');

        $this->customerRepository->method('findById')
            ->with($customerId)
            ->willReturn(null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage("Customer not found: 999");

        $this->handler->handle($command);
    }
}
