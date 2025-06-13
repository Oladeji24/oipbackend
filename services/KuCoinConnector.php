<?php
// KuCoinConnector.php
// Service for interacting with KuCoin API
namespace App\Services;

use GuzzleHttp\Client;

class KuCoinConnector {
    protected $client;
    protected $apiKey;
    protected $apiSecret;
    protected $apiPassphrase;

    public function __construct() {
        $this->client = new Client(['base_uri' => 'https://api.kucoin.com']);
        $this->apiKey = env('KUCOIN_API_KEY');
        $this->apiSecret = env('KUCOIN_API_SECRET');
        $this->apiPassphrase = env('KUCOIN_API_PASSPHRASE');
    }

    // Example: Get account balances
    public function getBalances() {
        // TODO: Implement KuCoin API authentication and request signing
        // See https://docs.kucoin.com/#api-key-authentication
        return $this->client->get('/api/v1/accounts', [
            'headers' => $this->getAuthHeaders('GET', '/api/v1/accounts', ''),
        ]);
    }

    // Place order (buy/sell/stop-limit)
    public function placeOrder($symbol, $side, $type = 'market', $size, $price = null, $stop = null, $stopPrice = null)
    {
        $endpoint = '/api/v1/orders';
        $bodyArr = [
            'symbol' => $symbol,
            'side' => $side,
            'type' => $type,
            'size' => $size
        ];
        if ($type === 'limit' && $price) {
            $bodyArr['price'] = $price;
        }
        if ($type === 'stop_limit' && $price && $stop && $stopPrice) {
            $bodyArr['price'] = $price;
            $bodyArr['stop'] = $stop; // e.g. 'entry' or 'loss'
            $bodyArr['stopPrice'] = $stopPrice;
        }
        $body = json_encode($bodyArr);
        return $this->client->post($endpoint, [
            'headers' => $this->getAuthHeaders('POST', $endpoint, $body),
            'body' => $body
        ]);
    }

    // Get market ticker for a symbol
    public function getTicker($symbol)
    {
        $endpoint = '/api/v1/market/orderbook/level1?symbol=' . $symbol;
        return $this->client->get($endpoint);
    }

    // Get order book (level 2) for a symbol
    public function getOrderBook($symbol)
    {
        $endpoint = '/api/v1/market/orderbook/level2_20?symbol=' . $symbol;
        return $this->client->get($endpoint);
    }

    // Get available symbols (markets)
    public function getSymbols()
    {
        $endpoint = '/api/v1/symbols';
        return $this->client->get($endpoint);
    }

    // Get recent trades for a symbol
    public function getTrades($symbol)
    {
        $endpoint = '/api/v1/market/histories?symbol=' . $symbol;
        return $this->client->get($endpoint);
    }

    // Enhanced: Get symbol details with error handling, caching, and logging
    public function getSymbol($symbol)
    {
        try {
            $cacheKey = 'kucoin_symbol_' . $symbol;
            // Try cache first (5 min)
            if (\Cache::has($cacheKey)) {
                return response()->json(\Cache::get($cacheKey));
            }
            $endpoint = '/api/v1/symbols/' . $symbol;
            $response = $this->client->get($endpoint);
            $data = json_decode($response->getBody(), true);
            if (isset($data['code']) && $data['code'] !== '200000') {
                \Log::error('KuCoin symbol fetch error', ['symbol' => $symbol, 'response' => $data]);
                throw new \Exception($data['msg'] ?? 'Unknown error from KuCoin');
            }
            // Cache for 5 minutes
            \Cache::put($cacheKey, $data, 300);
            return response()->json($data);
        } catch (\Exception $e) {
            \Log::error('KuCoin symbol fetch exception', ['symbol' => $symbol, 'error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // Add a method to clear the cache for a symbol (for admin or on-demand refresh)
    public function clearSymbolCache($symbol)
    {
        $cacheKey = 'kucoin_symbol_' . $symbol;
        \Cache::forget($cacheKey);
        return response()->json(['success' => true, 'message' => 'Cache cleared for ' . $symbol]);
    }

    // Get historical data for a symbol (mocked for demo)
    public function getHistoricalData($symbol, $limit = 50)
    {
        // TODO: Replace with real KuCoin API call for historical candles
        // For now, return an array of {close: ...} objects
        $data = [];
        $price = 50000;
        for ($i = 0; $i < $limit; $i++) {
            $price += rand(-1000, 1000);
            $data[] = ['close' => $price];
        }
        return $data;
    }

    private function getAuthHeaders($method, $endpoint, $body) {
        $timestamp = (string)(int)(microtime(true) * 1000);
        $strForSign = $timestamp . strtoupper($method) . $endpoint . $body;
        $signature = base64_encode(hash_hmac('sha256', $strForSign, $this->apiSecret, true));
        $passphrase = base64_encode(hash_hmac('sha256', $this->apiPassphrase, $this->apiSecret, true));
        return [
            'KC-API-KEY' => $this->apiKey,
            'KC-API-SIGN' => $signature,
            'KC-API-TIMESTAMP' => $timestamp,
            'KC-API-PASSPHRASE' => $passphrase,
            'KC-API-KEY-VERSION' => '2',
            'Content-Type' => 'application/json',
        ];
    }
}
