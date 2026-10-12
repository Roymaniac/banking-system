<?php

declare(strict_types=1);

namespace Transaction\Infrastructure\Transfer;

use Account\Domain\Account\ValueObject\AccountId;
use Account\Domain\Account\ValueObject\AccountNumber;
use Account\Domain\Account\ValueObject\AccountStatus;
use Account\Domain\Account\ValueObject\CurrencyCode;
use Illuminate\Database\ConnectionInterface;
use Transaction\Application\Transfer\RecipientAccountLookup;
use Transaction\Application\Transfer\View\RecipientAccountView;

/** Resolves an active compatible recipient into a privacy-safe display view. */
final readonly class DatabaseRecipientAccountLookup implements RecipientAccountLookup
{
    public function __construct(private ConnectionInterface $connection) {}

    public function findCompatible(
        AccountNumber $number,
        CurrencyCode $currency,
    ): ?RecipientAccountView {
        $recipient = $this->connection->table('accounts')
            ->join('customers', 'customers.id', '=', 'accounts.customer_id')
            ->where('accounts.number', $number->value())
            ->where('accounts.currency', $currency->value())
            ->where('accounts.status', AccountStatus::Active->value)
            ->first([
                'accounts.id',
                'accounts.number',
                'accounts.currency',
                'customers.first_name',
                'customers.last_name',
            ]);

        if ($recipient === null) {
            return null;
        }

        // A first name and last initial let the sender recognize the recipient
        // without turning this endpoint into a customer-directory lookup.
        $displayName = trim((string) $recipient->first_name).' '
            .mb_strtoupper(mb_substr(trim((string) $recipient->last_name), 0, 1)).'.';

        return new RecipientAccountView(
            new AccountId($recipient->id),
            $displayName,
            substr((string) $recipient->number, -4),
            (string) $recipient->currency,
        );
    }
}
