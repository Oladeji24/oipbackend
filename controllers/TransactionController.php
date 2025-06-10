<?php
// TransactionController.php
// Controller for logging and retrieving user actions and transactions

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\TransactionLogger;

class TransactionController extends Controller
{
    protected $logger;

    public function __construct(TransactionLogger $logger)
    {
        $this->logger = $logger;
    }

    // Log a user action
    public function logAction(Request $request)
    {
        $userId = $request->user()->id ?? 1;
        $action = $request->input('action');
        $details = $request->input('details', []);
        $this->logger->logAction($userId, $action, $details);
        return response()->json(['success' => true]);
    }

    // Log a transaction
    public function logTransaction(Request $request)
    {
        $userId = $request->user()->id ?? 1;
        $type = $request->input('type');
        $amount = $request->input('amount');
        $currency = $request->input('currency');
        $status = $request->input('status');
        $details = $request->input('details', []);
        $this->logger->logTransaction($userId, $type, $amount, $currency, $status, $details);
        return response()->json(['success' => true]);
    }
}
