<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;

final class DatabaseSeeder extends Seeder
{
    /** Loads a complete but clearly non-production banking demo. */
    public function run(): void
    {
        if (
            ! in_array(app()->environment(), ['local', 'testing'], true)
            && ! config('demo.seeding_enabled')
        ) {
            throw new RuntimeException('Demo banking data may only be seeded locally or during tests.');
        }

        $this->call(DemoBankingSeeder::class);
    }
}
