<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Kraken (Crypto)
Route::get('/kraken/balance', [App\Http\Controllers\KrakenController::class, 'balance']);
Route::post('/kraken/order', [App\Http\Controllers\KrakenController::class, 'placeOrder']);

// Alpaca (Forex)
Route::get('/alpaca/account', [App\Http\Controllers\AlpacaController::class, 'account']);
Route::post('/alpaca/order', [App\Http\Controllers\AlpacaController::class, 'placeOrder']);

// Paystack (NGN Payments)
Route::post('/paystack/initialize', [App\Http\Controllers\PaystackController::class, 'initialize']);

// PayPal (International Payments)
Route::post('/paypal/create-payment', [App\Http\Controllers\PayPalController::class, 'createPayment']);

// SendGrid (Email/OTP)
Route::post('/sendgrid/send', [App\Http\Controllers\SendGridController::class, 'send']);

// Bot Trading
Route::post('/bot/start', [App\Http\Controllers\BotController::class, 'start']);
Route::post('/bot/stop', [App\Http\Controllers\BotController::class, 'stop']);
Route::post('/bot/switch', [App\Http\Controllers\BotController::class, 'switch']);

// Transaction Logging
Route::post('/log/action', [App\Http\Controllers\TransactionController::class, 'logAction']);
Route::post('/log/transaction', [App\Http\Controllers\TransactionController::class, 'logTransaction']);

// Withdrawals (OTP/email + admin approval)
Route::post('/withdrawal/request', [App\Http\Controllers\WithdrawalController::class, 'requestWithdrawal']);
Route::post('/withdrawal/confirm', [App\Http\Controllers\WithdrawalController::class, 'confirmWithdrawal']);
Route::post('/withdrawal/approve', [App\Http\Controllers\WithdrawalController::class, 'approveWithdrawal']);

// Wallet Management
Route::get('/wallet/balance', [App\Http\Controllers\WalletController::class, 'balance']);
Route::get('/wallet/logs', [App\Http\Controllers\WalletController::class, 'logs']);
Route::post('/wallet/deposit', [App\Http\Controllers\WalletController::class, 'deposit']);
Route::post('/wallet/withdraw', [App\Http\Controllers\WalletController::class, 'withdraw']);

// User Management (admin)
Route::get('/users', [App\Http\Controllers\UserController::class, 'index']);
Route::get('/users/{id}', [App\Http\Controllers\UserController::class, 'show']);
Route::post('/users/{id}/flag', [App\Http\Controllers\UserController::class, 'flag']);
