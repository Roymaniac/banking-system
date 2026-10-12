<?php

declare(strict_types=1);

return [
    // Keep demo identities disabled unless a developer explicitly enables
    // them for a one-off seed command in a production-like local container.
    'seeding_enabled' => (bool) env('DEMO_SEEDING_ENABLED', false),
];
