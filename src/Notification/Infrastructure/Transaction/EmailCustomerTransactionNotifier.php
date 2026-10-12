<?php

declare(strict_types=1);

namespace Notification\Infrastructure\Transaction;

use Account\Domain\Account\ValueObject\AccountId;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Ledger\Domain\Entry\ValueObject\LedgerEntryId;
use Ledger\Domain\Ledger\ValueObject\LedgerCurrency;
use Notification\Application\Email\EmailSender;
use Notification\Application\Template\EmailTemplate;
use Notification\Application\Template\EmailTemplateRenderer;
use Notification\Domain\Email\ValueObject\RecipientEmail;
use Transaction\Application\Notification\CustomerTransactionActivity;
use Transaction\Application\Notification\CustomerTransactionNotifier;
use Transaction\Domain\Common\ValueObject\TransactionAmount;

/** Resolves a customer account and queues its encrypted transaction email. */
final readonly class EmailCustomerTransactionNotifier implements CustomerTransactionNotifier
{
    public function __construct(
        private ConnectionInterface $connection,
        private EmailTemplateRenderer $templates,
        private EmailSender $emails,
    ) {}

    public function sendToAccount(
        AccountId $accountId,
        CustomerTransactionActivity $activity,
        TransactionAmount $amount,
        DateTimeImmutable $occurredAt,
    ): void {
        $recipient = $this->connection->table('accounts')
            ->join('customers', 'customers.id', '=', 'accounts.customer_id')
            ->join('users', 'users.identity_user_id', '=', 'customers.user_id')
            ->where('accounts.id', $accountId->value())
            ->select(['accounts.number', 'users.email'])
            ->first();

        // Internal settlement accounts have ledgers but no customer identity.
        if ($recipient === null) {
            return;
        }

        $number = is_string($recipient->number) ? $recipient->number : '';
        $message = $this->templates->render(
            EmailTemplate::TransactionAlert,
            new RecipientEmail((string) $recipient->email),
            [
                'activity' => $this->activity($activity),
                'amount' => $this->formattedAmount($amount),
                'maskedAccount' => $number === '' ? 'unassigned account' : 'account ending '.substr($number, -4),
                'occurredAt' => $occurredAt->format('Y-m-d H:i T'),
            ],
        );

        $this->emails->send($message);
    }

    public function sendReversal(
        LedgerEntryId $originalEntryId,
        DateTimeImmutable $occurredAt,
    ): void {
        $deposit = $this->connection->table('deposits')
            ->where('ledger_entry_id', $originalEntryId->value())
            ->first();

        if ($deposit !== null) {
            $this->sendStoredReversal($deposit, CustomerTransactionActivity::ReversalDebited, $occurredAt);

            return;
        }

        $withdrawal = $this->connection->table('withdrawals')
            ->where('ledger_entry_id', $originalEntryId->value())
            ->first();

        if ($withdrawal !== null) {
            $this->sendStoredReversal($withdrawal, CustomerTransactionActivity::ReversalCredited, $occurredAt);

            return;
        }

        $transfer = $this->connection->table('transfers')
            ->where('ledger_entry_id', $originalEntryId->value())
            ->first();

        if ($transfer !== null) {
            $amount = new TransactionAmount(
                (int) $transfer->minor_units,
                new LedgerCurrency((string) $transfer->currency),
            );
            $this->sendToAccount(
                new AccountId((string) $transfer->sender_account_id),
                CustomerTransactionActivity::ReversalCredited,
                $amount,
                $occurredAt,
            );
            $this->sendToAccount(
                new AccountId((string) $transfer->recipient_account_id),
                CustomerTransactionActivity::ReversalDebited,
                $amount,
                $occurredAt,
            );

            return;
        }

        $multipleTransfer = $this->connection->table('multiple_transfers')
            ->where('ledger_entry_id', $originalEntryId->value())
            ->first();

        if ($multipleTransfer === null) {
            return;
        }

        $currency = new LedgerCurrency((string) $multipleTransfer->currency);
        $this->sendToAccount(
            new AccountId((string) $multipleTransfer->sender_account_id),
            CustomerTransactionActivity::ReversalCredited,
            new TransactionAmount((int) $multipleTransfer->total_minor_units, $currency),
            $occurredAt,
        );

        $items = $this->connection->table('multiple_transfer_items')
            ->where('multiple_transfer_id', $multipleTransfer->id)
            ->get();

        foreach ($items as $item) {
            $this->sendToAccount(
                new AccountId((string) $item->recipient_account_id),
                CustomerTransactionActivity::ReversalDebited,
                new TransactionAmount((int) $item->minor_units, $currency),
                $occurredAt,
            );
        }
    }

    private function sendStoredReversal(
        object $transaction,
        CustomerTransactionActivity $activity,
        DateTimeImmutable $occurredAt,
    ): void {
        $this->sendToAccount(
            new AccountId((string) $transaction->account_id),
            $activity,
            new TransactionAmount(
                (int) $transaction->minor_units,
                new LedgerCurrency((string) $transaction->currency),
            ),
            $occurredAt,
        );
    }

    private function activity(CustomerTransactionActivity $activity): string
    {
        return match ($activity) {
            CustomerTransactionActivity::DepositReceived => 'Deposit received',
            CustomerTransactionActivity::WithdrawalCompleted => 'Withdrawal completed',
            CustomerTransactionActivity::TransferSent => 'Transfer sent',
            CustomerTransactionActivity::TransferReceived => 'Transfer received',
            CustomerTransactionActivity::MultipleTransferSent => 'Multiple transfer sent',
            CustomerTransactionActivity::ReversalCredited => 'Transaction reversal credited',
            CustomerTransactionActivity::ReversalDebited => 'Transaction reversal debited',
        };
    }

    private function formattedAmount(TransactionAmount $amount): string
    {
        $minorUnits = $amount->minorUnits();

        return sprintf(
            '%s %d.%02d',
            $amount->currency()->value(),
            intdiv($minorUnits, 100),
            $minorUnits % 100,
        );
    }
}
