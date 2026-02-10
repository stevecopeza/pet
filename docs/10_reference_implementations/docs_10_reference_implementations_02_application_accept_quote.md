# Reference Implementation – Accept Quote Use Case

## Purpose
Shows how a **user intent** flows from UI to domain safely using commands and handlers.

---

## Flow

UI → Command → Handler → Domain → Event → Persistence

---

## Example

```php
final class AcceptQuoteHandler
{
    public function handle(AcceptQuote $command): void
    {
        $this->transaction->run(function () use ($command) {
            $quote = $this->quotes->get($command->quoteId);
            $event = $quote->accept($command->actor);
            $this->quotes->save($quote);
            $this->events->record($event);
        });
    }
}
```

---

## Key Rules

- Transaction boundary here
- Domain emits events
- Handler persists both state and event

---

**Authority**: Reference

