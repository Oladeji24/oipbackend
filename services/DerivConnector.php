<?php
// DerivConnector.php
// Service for interacting with Deriv API
namespace App\Services;

use GuzzleHttp\Client;

class DerivConnector {
    protected $client;
    protected $apiToken;

    public function __construct() {
        $this->client = new Client(['base_uri' => 'https://api.deriv.com']);
        $this->apiToken = env('DERIV_API_TOKEN');
    }

    // Example: Get account info
    public function getAccountInfo() {
        return $this->client->post('/websockets/v3', [
            'json' => [
                'authorize' => $this->apiToken
            ]
        ]);
    }

    // Place order (buy/sell)
    public function placeOrder($symbol, $side, $amount)
    {
        // Example Deriv API call for placing a trade (simplified)
        $endpoint = '/websockets/v3';
        $bodyArr = [
            'authorize' => $this->apiToken,
            'buy' => 1,
            'price' => $amount,
            'symbol' => $symbol,
            'side' => $side
        ];
        $body = json_encode($bodyArr);
        return $this->client->post($endpoint, [
            'body' => $body,
            'headers' => [ 'Content-Type' => 'application/json' ]
        ]);
    }

    // Get available symbols (markets)
    public function getSymbols()
    {
        $endpoint = '/trading_symbols';
        return $this->client->get($endpoint);
    }

    // Get recent trades for a symbol (mocked, as Deriv is websocket-based)
    public function getTrades($symbol)
    {
        // Deriv's REST API is limited; for real trades, use websocket or mock
        return response()->json(['success' => true, 'message' => 'Not implemented: Use websocket for live trades.']);
    }

    // Enhanced: Get symbol details with error handling, caching, and logging
    public function getSymbol($symbol)
    {
        try {
            $cacheKey = 'deriv_symbol_' . $symbol;
            // Try cache first (5 min)
            if (\Cache::has($cacheKey)) {
                return response()->json(\Cache::get($cacheKey));
            }
            $endpoint = '/trading_symbols';
            $response = $this->client->get($endpoint);
            $data = json_decode($response->getBody(), true);
            if (!isset($data['trading_symbols'])) {
                \Log::error('Deriv symbol fetch error', ['symbol' => $symbol, 'response' => $data]);
                throw new \Exception('Unknown error from Deriv');
            }
            $symbolInfo = collect($data['trading_symbols'])->firstWhere('symbol', $symbol);
            if (!$symbolInfo) {
                throw new \Exception('Symbol not found');
            }
            // Cache for 5 minutes
            \Cache::put($cacheKey, $symbolInfo, 300);
            return response()->json($symbolInfo);
        } catch (\Exception $e) {
            \Log::error('Deriv symbol fetch exception', ['symbol' => $symbol, 'error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // Add a method to clear the cache for a symbol (for admin or on-demand refresh)
    public function clearSymbolCache($symbol)
    {
        $cacheKey = 'deriv_symbol_' . $symbol;
        \Cache::forget($cacheKey);
        return response()->json(['success' => true, 'message' => 'Cache cleared for ' . $symbol]);
    }

    // Get historical data for a symbol (mocked for demo)
    public function getHistoricalData($symbol, $limit = 50)
    {
        // TODO: Replace with real Deriv API call for historical candles
        // For now, return an array of {close: ...} objects
        $data = [];
        $price = 1.1000;
        for ($i = 0; $i < $limit; $i++) {
            $price += (rand(-100, 100) / 10000);
            $data[] = ['close' => round($price, 5)];
        }
        return $data;
    }

    // Add more methods for getting market data, etc.
}
