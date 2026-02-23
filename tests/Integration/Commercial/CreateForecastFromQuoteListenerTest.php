<?php

declare(strict_types=1);

namespace Pet\Tests\Integration\Commercial;

use PHPUnit\Framework\TestCase;
use Pet\Application\Commercial\Listener\CreateForecastFromQuoteListener;
use Pet\Domain\Commercial\Entity\Forecast;
use Pet\Domain\Commercial\Entity\Quote;
use Pet\Domain\Commercial\Entity\Component\CatalogComponent;
use Pet\Domain\Commercial\Entity\Component\QuoteCatalogItem;
use Pet\Domain\Commercial\Event\QuoteAccepted;
use Pet\Domain\Commercial\ValueObject\QuoteState;

final class CreateForecastFromQuoteListenerTest extends TestCase
{
    public function testCreatesCommittedForecastOnQuoteAccepted(): void
    {
        $quoteId = 500;

        $item = new QuoteCatalogItem(
            'Consulting Day',
            2.0,
            1000.0,
            600.0
        );
        $component = new CatalogComponent([$item], 'Consulting');

        $quote = new Quote(
            10,
            'Forecast Test Quote',
            'Forecast path',
            QuoteState::accepted(),
            1,
            2000.0,
            1200.0,
            'USD',
            new \DateTimeImmutable('2026-01-01 10:00:00'),
            $quoteId,
            new \DateTimeImmutable('2026-01-01 09:00:00'),
            new \DateTimeImmutable('2026-01-01 09:00:00'),
            null,
            [$component],
            [],
            [],
            []
        );

        $captured = null;

        $repo = new class($captured) implements \Pet\Domain\Commercial\Repository\ForecastRepository {
            private $captured;

            public function __construct(& $captured)
            {
                $this->captured = & $captured;
            }

            public function save(Forecast $forecast): void
            {
                $this->captured = $forecast;
            }

            public function findByQuoteId(int $quoteId): ?Forecast
            {
                return $this->captured && $this->captured->quoteId() === $quoteId
                    ? $this->captured
                    : null;
            }

            public function findAll(): array
            {
                return $this->captured ? [$this->captured] : [];
            }
        };

        $listener = new CreateForecastFromQuoteListener($repo);

        $event = new QuoteAccepted($quote);
        $listener($event);

        $this->assertInstanceOf(Forecast::class, $captured);
        $this->assertSame($quoteId, $captured->quoteId());
        $this->assertSame(2000.0, $captured->totalValue());
        $this->assertSame(1.0, $captured->probability());
        $this->assertSame('committed', $captured->status());

        $breakdown = $captured->breakdown();
        $this->assertArrayHasKey('catalog', $breakdown);
        $this->assertSame(2000.0, $breakdown['catalog']);
    }
}

