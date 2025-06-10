<?php
// TransactionLogger.php
// Service for logging all user actions and transactions (audit trail)
// Logs to Supabase or local database

namespace App\Services;

use Illuminate\Support\Facades\Log;

class TransactionLogger {
    // Log a generic user action
    public function logAction($userId, $action, $details = []) {
        // TODO: Optionally send to Supabase or use Laravel logs/database
        Log::info('User Action', [
            'user_id' => $userId,
            'action' => $action,
            'details' => $details,
            'timestamp' => now(),
        ]);
    }

    // Log a transaction (deposit, withdrawal, trade, etc.)
    public function logTransaction($userId, $type, $amount, $currency, $status, $details = []) {
        Log::info('Transaction', [
            'user_id' => $userId,
            'type' => $type,
            'amount' => $amount,
            'currency' => $currency,
            'status' => $status,
            'details' => $details,
            'timestamp' => now(),
        ]);
    }
}
