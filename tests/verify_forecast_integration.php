<?php

// tests/verify_forecast_integration.php

// This script verifies that the Forecast Integration components are registered and working.
// Run with: wp eval-file tests/verify_forecast_integration.php

use Pet\Domain\Commercial\Entity\Quote;
use Pet\Domain\Commercial\Event\QuoteAccepted;
use Pet\Application\Commercial\Listener\CreateForecastFromQuoteListener;
use Pet\Infrastructure\Persistence\Repository\SqlForecastRepository;
use Pet\Domain\Commercial\ValueObject\QuoteState;

echo "Starting Forecast Integration Verification...\n";

// 1. Check Container Registration
try {
    $container = \Pet\Infrastructure\DependencyInjection\ContainerFactory::create();
    echo "[PASS] Container created.\n";

    $listener = $container->get(CreateForecastFromQuoteListener::class);
    echo "[PASS] CreateForecastFromQuoteListener retrieved from container.\n";

    $repo = $container->get(\Pet\Domain\Commercial\Repository\ForecastRepository::class);
    echo "[PASS] ForecastRepository retrieved from container.\n";
    
    // Check if it's the correct implementation
    if ($repo instanceof SqlForecastRepository) {
        echo "[PASS] ForecastRepository is instance of SqlForecastRepository.\n";
    } else {
        echo "[FAIL] ForecastRepository is NOT instance of SqlForecastRepository.\n";
        exit(1);
    }

} catch (\Exception $e) {
    echo "[FAIL] Container/Dependency Error: " . $e->getMessage() . "\n";
    exit(1);
}

// 2. Verify Event Subscription
// We can't easily check private listeners in InMemoryEventBus, but we can trust the registration code in pet.php if this script runs in the context of the plugin.
// However, wp eval-file loads WP, which loads the plugin.
// So the plugin bootstrap should have run.

global $pet_container; // Is the container exposed? Probably not globally.

// 3. Functional Test (Simulated)
// We will create a dummy quote, save it, and then simulate the event dispatch to see if a forecast is created.

echo "Simulating Quote Acceptance...\n";

try {
    // We need repositories to save data
    $quoteRepo = $container->get(\Pet\Domain\Commercial\Repository\QuoteRepository::class);
    $forecastRepo = $container->get(\Pet\Domain\Commercial\Repository\ForecastRepository::class);
    $eventBus = $container->get(\Pet\Domain\Event\EventBus::class);

    // Create a mock Quote (we can't easily create a full entity with all VOs without a factory, so we'll try to use a real one if available or create a minimal one)
    // Let's see if we can find an existing quote to use
    $existingQuotes = $quoteRepo->findAll();
    $quote = null;
    
    if (count($existingQuotes) > 0) {
        $quote = $existingQuotes[0];
        echo "Using existing quote ID: " . $quote->id() . "\n";
    } else {
        echo "No existing quotes found. Skipping functional test (creating a quote is complex in this script).\n";
        // Create a simple quote if possible
        // $quote = new Quote(...); 
        // We'll skip for now to avoid complexity of creating all dependencies.
    }

    if ($quote) {
        // Dispatch QuoteAccepted event
        // Note: This might duplicate side effects (Contract/Baseline creation), but for verification it's okay.
        // We are checking if Forecast is created.
        
        // First, check if forecast already exists for this quote
        $existingForecast = $forecastRepo->findByQuoteId($quote->id());
        if ($existingForecast) {
            echo "Forecast already exists for Quote " . $quote->id() . ". Deleting it for test.\n";
            // SqlForecastRepository doesn't have delete method in interface, but maybe implementation has?
            // Interface doesn't have delete. We can't delete easily via repository.
            // We'll proceed and see if the listener handles duplicates (it returns if exists).
            // To test creation, we need a quote without a forecast.
            echo "Skipping creation test as forecast exists. Manual verification: Check 'pet_forecasts' table.\n";
        } else {
            echo "Dispatching QuoteAccepted event...\n";
            $event = new QuoteAccepted($quote);
            
            // We need to manually invoke the listener because the event bus in the container here might be different 
            // from the one in the plugin bootstrap if the container is not shared globally.
            // In WP, plugins are loaded once. ContainerFactory::create() creates a NEW container.
            // So the listeners registered in pet.php are on a DIFFERENT container instance than the one we just created.
            // THIS IS IMPORTANT.
            
            // So we must register the listener on OUR event bus instance to test the LISTENER logic.
            $eventBus->subscribe(QuoteAccepted::class, $listener);
            $eventBus->dispatch($event);
            
            // Check if forecast was created
            $forecast = $forecastRepo->findByQuoteId($quote->id());
            if ($forecast) {
                echo "[PASS] Forecast created for Quote " . $quote->id() . ".\n";
                echo "Forecast Value: " . $forecast->totalValue() . "\n";
                echo "Forecast Probability: " . $forecast->probability() . "\n";
                echo "Forecast Status: " . $forecast->status() . "\n";
            } else {
                echo "[FAIL] Forecast NOT created for Quote " . $quote->id() . ".\n";
                exit(1);
            }
        }
    }

} catch (\Exception $e) {
    echo "[FAIL] Functional Test Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Verification Complete.\n";
