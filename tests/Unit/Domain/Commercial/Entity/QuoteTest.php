<?php

declare(strict_types=1);

namespace Pet\Tests\Unit\Domain\Commercial\Entity;

use PHPUnit\Framework\TestCase;
use Pet\Domain\Commercial\Entity\Quote;
use Pet\Domain\Commercial\Entity\PaymentMilestone;
use Pet\Domain\Commercial\Entity\Component\QuoteComponent;
use Pet\Domain\Commercial\Entity\Component\ImplementationComponent;
use Pet\Domain\Commercial\Entity\Component\CatalogComponent;
use Pet\Domain\Commercial\Entity\Component\QuoteCatalogItem;
use Pet\Domain\Commercial\ValueObject\QuoteState;
use Pet\Domain\Commercial\Entity\Component\Milestone; // Assuming Milestone class exists, need to check
use Pet\Domain\Commercial\Entity\Component\Task; // Assuming Task class exists, need to check

class QuoteTest extends TestCase
{
    public function testRecalculateTotalsSumComponents()
    {
        $quote = new Quote(
            1, // customerId
            'Test Quote',
            'Description',
            QuoteState::draft(),
            1, // revision
            0.00, // totalValue
            0.00, // totalInternalCost
            'USD',
            null,
            null
        );

        $component1 = $this->createMock(QuoteComponent::class);
        $component1->method('sellValue')->willReturn(100.0);
        $component1->method('internalCost')->willReturn(50.0);
        $component1->method('id')->willReturn(1);

        $component2 = $this->createMock(QuoteComponent::class);
        $component2->method('sellValue')->willReturn(200.0);
        $component2->method('internalCost')->willReturn(80.0);
        $component2->method('id')->willReturn(2);

        $quote->addComponent($component1);
        $quote->addComponent($component2);

        $this->assertEquals(300.0, $quote->totalValue());
        $this->assertEquals(130.0, $quote->totalInternalCost());
    }

    public function testValidateReadinessThrowsIfProductItemHasWbs()
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage("Product item 'Product Item' cannot have service-only fields (WBS).");

        $quote = new Quote(
            1, 'Title', 'Desc', QuoteState::draft(), 1, 0, 0, 'USD', null, null
        );

        $item = new QuoteCatalogItem('Product Item', 1, 100, 50, null, null, ['some' => 'wbs'], 'product', 'SKU-123');
        $component = new CatalogComponent([$item], 'Catalog Comp');
        
        $quote->addComponent($component);
        
        // Satisfy other constraints
        $quote->setPaymentSchedule([new PaymentMilestone('Full', 100, null, false)]);
        
        $quote->validateReadiness();
    }

    public function testValidateReadinessPassesWhenValid()
    {
        $quote = new Quote(
            1, 'Title', 'Desc', QuoteState::draft(), 1, 0, 0, 'USD', null, null
        );

        // Valid Product
        $productItem = new QuoteCatalogItem('Product Item', 1, 100, 50, null, null, [], 'product', 'PROD-123');
        $catalogComp = new CatalogComponent([$productItem], 'Catalog Comp');
        $quote->addComponent($catalogComp);

        // Valid Service
        $serviceItem = new QuoteCatalogItem('Service Item', 1, 100, 50, null, null, ['wbs' => 'data'], 'service', 'SERV-456', 101);
        $serviceComp = new CatalogComponent([$serviceItem], 'Service Comp');
        $quote->addComponent($serviceComp);

        // Valid Implementation
        // We need to mock ImplementationComponent or construct it fully if possible.
        // Assuming ImplementationComponent logic for milestones/tasks is accessible.
        // For simplicity, let's just use Catalog components as they cover the new type check.
        
        $quote->setPaymentSchedule([new PaymentMilestone('Full', 200, null, false)]);

        $quote->validateReadiness();
        $this->assertTrue(true); // Should reach here
    }

    public function testRemoveComponentRecalculatesTotals()
    {
        $quote = new Quote(
            1,
            'Test Quote',
            'Description',
            QuoteState::draft(),
            1,
            0.00,
            0.00,
            'USD',
            null,
            null
        );

        $component = $this->createMock(QuoteComponent::class);
        $component->method('sellValue')->willReturn(100.0);
        $component->method('internalCost')->willReturn(50.0);
        $component->method('id')->willReturn(123);

        $quote->addComponent($component);
        $this->assertEquals(100.0, $quote->totalValue());

        $quote->removeComponent(123);
        $this->assertEquals(0.0, $quote->totalValue());
        $this->assertEquals(0.0, $quote->totalInternalCost());
    }

    public function testUpdateDoesNotAcceptTotalValue()
    {
        $quote = new Quote(
            1,
            'Test Quote',
            'Description',
            QuoteState::draft(),
            1,
            100.00, // Initial value
            50.00,
            'USD',
            null,
            null
        );

        // Update with new currency
        $quote->update(
            2, // new customer
            'EUR',
            null,
            []
        );

        $this->assertEquals('EUR', $quote->currency());
        // Total value should remain unchanged if no components changed
        $this->assertEquals(100.00, $quote->totalValue());
    }

    public function testValidateReadinessRequiresPaymentSchedule()
    {
        $quote = new Quote(
            1,
            'Test Quote',
            'Description',
            QuoteState::draft()
        );

        $component = $this->createMock(QuoteComponent::class);
        $component->method('sellValue')->willReturn(100.0);
        $quote->addComponent($component);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Quote must have a payment schedule.');

        $quote->validateReadiness();
    }

    public function testValidateReadinessRequiresPaymentScheduleMatchTotal()
    {
        $quote = new Quote(
            1,
            'Test Quote',
            'Description',
            QuoteState::draft()
        );

        $component = $this->createMock(QuoteComponent::class);
        $component->method('sellValue')->willReturn(100.0);
        $quote->addComponent($component);

        // Schedule only covers 50
        $quote->setPaymentSchedule([
            new PaymentMilestone('Deposit', 50.0)
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Payment schedule total (50.00) must match quote total value (100.00).');

        $quote->validateReadiness();
    }

    public function testValidateReadinessPassesWithValidSchedule()
    {
        $quote = new Quote(
            1,
            'Test Quote',
            'Description',
            QuoteState::draft()
        );

        $component = $this->createMock(QuoteComponent::class);
        $component->method('sellValue')->willReturn(100.0);
        $component->method('internalCost')->willReturn(50.0);
        $quote->addComponent($component);

        $quote->setPaymentSchedule([
            new PaymentMilestone('Deposit', 50.0),
            new PaymentMilestone('Balance', 50.0)
        ]);

        // Should not throw
        $quote->validateReadiness();
        $this->addToAssertionCount(1);
    }
}
