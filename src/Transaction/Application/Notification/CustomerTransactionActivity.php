<?php

declare(strict_types=1);

namespace Transaction\Application\Notification;

/** Describes how a completed money movement affected the customer's account. */
enum CustomerTransactionActivity: string
{
    case DepositReceived = 'deposit_received';
    case WithdrawalCompleted = 'withdrawal_completed';
    case TransferSent = 'transfer_sent';
    case TransferReceived = 'transfer_received';
    case MultipleTransferSent = 'multiple_transfer_sent';
    case ReversalCredited = 'reversal_credited';
    case ReversalDebited = 'reversal_debited';
}
