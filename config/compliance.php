<?php

declare(strict_types=1);

$fingerprintKey = env('KYC_DOCUMENT_FINGERPRINT_KEY');

return [
    // A separate secret prevents document fingerprints from depending on ciphertext.
    'document_fingerprint_key' => is_string($fingerprintKey) && trim($fingerprintKey) !== ''
        ? $fingerprintKey
        : env('APP_KEY'),
];
