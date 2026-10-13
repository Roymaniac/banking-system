<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

it('runs the container worker for notification and default jobs', function (): void {
    /** @var array{services: array{worker: array{command: list<string>}}} $compose */
    $compose = Yaml::parseFile(base_path('compose.yaml'));

    // Registration emails are processed on the named notifications queue.
    // The worker must listen there as well as on Laravel's default queue.
    expect($compose['services']['worker']['command'])
        ->toContain('--queue=notifications,default');
});
