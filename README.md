# siqa/agent

SIQA Agent SDK for PHP.

## Installation

```bash
composer require siqa/agent
```

## Usage

```php
<?php
require 'vendor/autoload.php';

use SIQA\Agent\Client;

$client = Client::create(
    apiKey: 'your_api_key',
    brandId: 'your_brand_id'
);

// Initialise and auto-start heartbeat
$data = $client->init('https://example.com/page');
echo 'Mode: ' . $client->getMode() . PHP_EOL;

// Fetch schema recommendations
$schemas = $client->getSchemaRecommendations('https://example.com/page');

// Report injected schemas
$client->reportSchemaInjection('https://example.com/page', ['Organization']);

// Generate content brief
$brief = $client->generateContent(['brand_id' => 'your_brand_id', 'keyword' => 'AI visibility']);
print_r($brief);

// Stop background timers
$client->stop();
```

## API

- `init(pageUrl)` — Initialise the SDK and start heartbeats.
- `getSchemaRecommendations(pageUrl)` — Fetch pending JSON-LD schemas.
- `reportSchemaInjection(pageUrl, schemas)` — Confirm schemas injected into the DOM.
- `sendHeartbeat(pageUrl)` — Send a manual heartbeat.
- `submitPageReport(report)` — Submit a full page audit.
- `generateContent(opts)` — Generate an AEO-scored content brief.
- `pollCitations()` — Poll for citation changes.
- `stop()` — Stop background timers.
