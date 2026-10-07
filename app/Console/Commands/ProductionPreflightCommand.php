<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use Throwable;

/** Verifies that a release is safe to expose to production traffic. */
final class ProductionPreflightCommand extends Command
{
    protected $signature = 'banking:production-preflight';

    protected $description = 'Check production configuration and database connectivity';

    public function __construct(
        private readonly ConfigRepository $config,
        private readonly ConnectionInterface $database,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $checks = [
            'Application environment is production' => fn (): bool => $this->config->get('app.env') === 'production',
            'Debug mode is disabled' => fn (): bool => $this->config->get('app.debug') === false,
            'Application encryption key is configured' => fn (): bool => $this->hasApplicationKey(),
            'Application URL uses HTTPS' => fn (): bool => $this->usesHttps($this->config->get('app.url')),
            'Customer-facing URLs use HTTPS' => fn (): bool => $this->customerUrlsUseHttps(),
            'Queue uses a durable driver' => fn (): bool => $this->usesDurableDriver('queue.default', ['sync', 'null']),
            'Cache uses a shared driver' => fn (): bool => $this->usesDurableDriver('cache.default', ['array', 'null']),
            'Mail uses a delivery transport' => fn (): bool => $this->usesDurableDriver('mail.default', ['array', 'log']),
            'Settlement ledger identifiers are configured' => fn (): bool => $this->hasSettlementLedgers(),
            'Database accepts connections' => fn (): bool => $this->databaseIsAvailable(),
        ];

        $failed = 0;

        foreach ($checks as $name => $check) {
            if ($this->passes($check)) {
                $this->components->twoColumnDetail($name, '<fg=green>PASS</>');

                continue;
            }

            $failed++;
            $this->components->twoColumnDetail($name, '<fg=red>FAIL</>');
        }

        if ($failed > 0) {
            $this->newLine();
            $this->error("Production preflight failed {$failed} check(s). Traffic must remain disabled.");

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Production preflight passed. The release may proceed to readiness verification.');

        return self::SUCCESS;
    }

    private function hasApplicationKey(): bool
    {
        $key = $this->config->get('app.key');

        return is_string($key) && trim($key) !== '';
    }

    private function customerUrlsUseHttps(): bool
    {
        return $this->usesHttps($this->config->get('notification.verification_url'))
            && $this->usesHttps($this->config->get('notification.password_reset_url'));
    }

    private function usesHttps(mixed $url): bool
    {
        return is_string($url) && str_starts_with(strtolower($url), 'https://');
    }

    /** @param list<string> $unsafeDrivers */
    private function usesDurableDriver(string $configurationKey, array $unsafeDrivers): bool
    {
        $driver = $this->config->get($configurationKey);

        return is_string($driver)
            && $driver !== ''
            && ! in_array(strtolower($driver), $unsafeDrivers, true);
    }

    private function hasSettlementLedgers(): bool
    {
        $ledgers = $this->config->get('banking.settlement_ledgers');

        if (! is_array($ledgers)) {
            return false;
        }

        $identifiers = [];

        foreach (['deposit', 'withdrawal'] as $operation) {
            foreach (['NGN', 'USD', 'GBP'] as $currency) {
                $identifier = $ledgers[$operation][$currency] ?? null;

                if (! is_string($identifier) || ! Str::isUuid($identifier)) {
                    return false;
                }

                $identifiers[] = strtolower($identifier);
            }
        }

        // Reusing one ledger for two purposes would corrupt settlement reporting.
        return count(array_unique($identifiers)) === count($identifiers);
    }

    private function databaseIsAvailable(): bool
    {
        $this->database->select('SELECT 1');

        return true;
    }

    /** @param callable(): bool $check */
    private function passes(callable $check): bool
    {
        try {
            return $check();
        } catch (Throwable) {
            // Command output must not reveal exception messages or credentials.
            return false;
        }
    }
}
