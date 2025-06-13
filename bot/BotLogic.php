<?php
// BotLogic.php
// Core trading bot logic for both Crypto (Kraken) and Forex (Alpaca)
// Handles trend detection, trade management, and user controls

namespace App\Bot;

use Illuminate\Support\Facades\DB;

class BotLogic {
    // Store user bot params (in-memory for demo; use DB in production)
    private $userParams = [];

    // In-memory open positions (for demo)
    protected $openPositions = [];

    // List of major pairs for crypto and forex
    private $majorPairs = [
        'crypto' => [
            'BTC-USDT', 'ETH-USDT', 'BNB-USDT', 'SOL-USDT', 'ADA-USDT',
            'XRP-USDT', 'DOGE-USDT', 'AVAX-USDT', 'MATIC-USDT', 'DOT-USDT'
        ],
        'forex' => [
            'EURUSD', 'USDJPY', 'GBPUSD', 'USDCHF', 'AUDUSD',
            'USDCAD', 'NZDUSD', 'EURJPY', 'GBPJPY', 'EURGBP'
        ]
    ];

    // Calculates EMA
    private function calculateEMA($prices, $period) {
        $k = 2 / ($period + 1);
        $ema = $prices[0];
        $emaArr = [$ema];
        for ($i = 1; $i < count($prices); $i++) {
            $ema = $prices[$i] * $k + $ema * (1 - $k);
            $emaArr[] = $ema;
        }
        return $emaArr;
    }

    // Calculates RSI
    private function calculateRSI($prices, $period) {
        $rsiArr = [];
        $gains = 0; $losses = 0;
        for ($i = 1; $i <= $period; $i++) {
            $diff = $prices[$i] - $prices[$i-1];
            if ($diff >= 0) $gains += $diff; else $losses -= $diff;
        }
        $gains /= $period;
        $losses /= $period;
        $rs = $gains / ($losses ?: 1);
        $rsiArr[$period] = 100 - 100 / (1 + $rs);
        for ($i = $period + 1; $i < count($prices); $i++) {
            $diff = $prices[$i] - $prices[$i-1];
            if ($diff >= 0) {
                $gains = ($gains * ($period - 1) + $diff) / $period;
                $losses = ($losses * ($period - 1)) / $period;
            } else {
                $gains = ($gains * ($period - 1)) / $period;
                $losses = ($losses * ($period - 1) - $diff) / $period;
            }
            $rs = $gains / ($losses ?: 1);
            $rsiArr[$i] = 100 - 100 / (1 + $rs);
        }
        return $rsiArr;
    }

    public function getBotAnalytics($userId) {
        $trades = DB::table('bot_trades')->where('user_id', $userId)->get();
        $total = $trades->count();
        $win = $trades->where('profit', '>', 0)->count();
        $loss = $trades->where('profit', '<', 0)->count();
        $profit = $trades->sum('profit');
        return [
            'total' => $total,
            'win' => $win,
            'loss' => $loss,
            'profit' => $profit,
            'winRate' => $total ? round($win / $total * 100, 2) : 0,
            'avgProfit' => $total ? round($profit / $total, 2) : 0,
        ];
    }

    public function updateBotParams($userId, $params) {
        $this->userParams[$userId] = array_merge($this->userParams[$userId] ?? [
            'emaFast' => 7,
            'emaSlow' => 14,
            'rsiPeriod' => 14,
            'riskLevel' => 1
        ], $params);
        return ['success' => true, 'params' => $this->userParams[$userId]];
    }

    public function getUserParams($userId) {
        return $this->userParams[$userId] ?? [
            'emaFast' => 7,
            'emaSlow' => 14,
            'rsiPeriod' => 14,
            'macdFast' => 12,
            'macdSlow' => 26,
            'macdSignal' => 9,
            'riskLevel' => 1,
            'tripleFast' => 5,
            'tripleMid' => 15,
            'tripleSlow' => 30
        ];
    }

    // Enhanced trend detection using selected strategy (EMA/RSI, MACD, Volume)
    public function detectTrend($market, $pairOrSymbol, $historicalData, $userId = null) {
        $params = $userId && isset($this->userParams[$userId]) ? $this->userParams[$userId] : [
            'strategy' => 'ema-rsi',
            'emaFast' => 7,
            'emaSlow' => 14,
            'rsiPeriod' => 14,
            'macdFast' => 12,
            'macdSlow' => 26,
            'macdSignal' => 9,
            'riskLevel' => 1,
            'tripleFast' => 5,
            'tripleMid' => 15,
            'tripleSlow' => 30
        ];
        $closes = array_map(function($p) { return $p['close']; }, $historicalData);
        // Strategy map for extensibility
        $strategy = $params['strategy'] ?? 'ema-rsi';
        if ($strategy === 'macd') {
            if (count($closes) < max($params['macdFast'], $params['macdSlow'], $params['macdSignal'])) return 'hold';
            $emaFast = $this->calculateEMA($closes, $params['macdFast']);
            $emaSlow = $this->calculateEMA($closes, $params['macdSlow']);
            $macd = array_map(function($f, $s) { return $f - $s; }, $emaFast, $emaSlow);
            $signal = $this->calculateEMA($macd, $params['macdSignal']);
            $last = count($closes) - 1;
            if ($macd[$last] > $signal[$last]) return 'buy';
            if ($macd[$last] < $signal[$last]) return 'sell';
            return 'hold';
        } elseif ($strategy === 'volume') {
            if (count($historicalData) < 10) return 'hold';
            $recent = array_slice($historicalData, -5);
            $avgVol = array_sum(array_map(function($p){return $p['volume'] ?? 0;}, array_slice($historicalData, -20))) / 20;
            $lastVol = $recent[count($recent)-1]['volume'] ?? 0;
            if ($lastVol > 1.5 * $avgVol && $recent[count($recent)-1]['close'] > $recent[0]['close']) return 'buy';
            if ($lastVol > 1.5 * $avgVol && $recent[count($recent)-1]['close'] < $recent[0]['close']) return 'sell';
            return 'hold';
        } elseif ($strategy === 'triple-ema') {
            if (count($closes) < max($params['tripleFast'], $params['tripleMid'], $params['tripleSlow'])) return 'hold';
            $emaFast = $this->calculateEMA($closes, $params['tripleFast']);
            $emaMid = $this->calculateEMA($closes, $params['tripleMid']);
            $emaSlow = $this->calculateEMA($closes, $params['tripleSlow']);
            $last = count($closes) - 1;
            if ($emaFast[$last] > $emaMid[$last] && $emaMid[$last] > $emaSlow[$last]) return 'buy';
            if ($emaFast[$last] < $emaMid[$last] && $emaMid[$last] < $emaSlow[$last]) return 'sell';
            return 'hold';
        } else {
            if (count($closes) < max($params['emaFast'], $params['emaSlow'], $params['rsiPeriod'])) return 'hold';
            $emaFast = $this->calculateEMA($closes, $params['emaFast']);
            $emaSlow = $this->calculateEMA($closes, $params['emaSlow']);
            $rsi = $this->calculateRSI($closes, $params['rsiPeriod']);
            $last = count($closes) - 1;
            if ($emaFast[$last] > $emaSlow[$last] && $rsi[$last] < 70 - 10 * ($params['riskLevel'] - 1)) return 'buy';
            if ($emaFast[$last] < $emaSlow[$last] && $rsi[$last] > 30 + 10 * ($params['riskLevel'] - 1)) return 'sell';
            return 'hold';
        }
    }

    // Placeholder for managing a trade
    public function manageTrade($userId, $market, $pairOrSymbol, $position, $targetProfit = 0.05) {
        // TODO: Check if position has reached 5% profit
        // If so, close position and log action
        // Otherwise, hold
        return [
            'action' => 'hold',
            'profitReached' => false,
        ];
    }

    // Placeholder for starting/stopping/switching bot
    public function startBot($userId, $market, $symbol) {
        if (!$this->isMajorPair($market, $symbol)) {
            return ['success' => false, 'message' => 'Only major pairs are allowed for trading.'];
        }
        // TODO: Start bot for user in selected market and symbol
        return ['success' => true, 'message' => "Bot started for $market on $symbol."];
    }

    public function stopBot($userId) {
        // TODO: Stop bot for user
        return ['success' => true, 'message' => 'Bot stopped.'];
    }

    public function switchMarket($userId, $newMarket) {
        // TODO: Switch bot market for user
        return ['success' => true, 'message' => "Switched to $newMarket."];
    }

    // Return all active bots (for demo, static list)
    public function getActiveBots() {
        // TODO: Replace with real DB/user tracking
        return [
            ['userId' => 1, 'market' => 'crypto', 'symbol' => 'BTC-USDT'],
            ['userId' => 2, 'market' => 'forex', 'symbol' => 'EURUSD'],
        ];
    }

    // Enhanced analytics for bot performance
    public function getBotAdvancedAnalytics($userId) {
        $trades = DB::table('bot_trades')->where('user_id', $userId)->get();
        $total = $trades->count();
        $win = $trades->where('profit', '>', 0)->count();
        $loss = $trades->where('profit', '<', 0)->count();
        $profit = $trades->sum('profit');
        $holdTimes = $trades->map(function($t){
            if ($t->closed_at && $t->opened_at) {
                return strtotime($t->closed_at) - strtotime($t->opened_at);
            }
            return null;
        })->filter()->all();
        $maxDrawdown = 0; $best = null; $worst = null; $streak = 0; $maxStreak = 0; $currentStreak = 0;
        $peak = 0; $trough = 0; $equity = 0;
        foreach ($trades as $t) {
            $p = $t->profit ?? 0;
            $profit += $p;
            if ($p > 0) { $win++; $currentStreak++; } else if ($p < 0) { $loss++; $currentStreak = 0; }
            if ($currentStreak > $maxStreak) $maxStreak = $currentStreak;
            if ($best === null || $p > $best) $best = $p;
            if ($worst === null || $p < $worst) $worst = $p;
            $equity += $p;
            if ($equity > $peak) $peak = $equity;
            if ($equity < $trough) $trough = $equity;
        }
        $maxDrawdown = $peak - $trough;
        $avgHold = count($holdTimes) ? array_sum($holdTimes) / count($holdTimes) : 0;
        $sharpe = $this->calculateSharpeRatio($trades);
        return [
            'total' => $total,
            'win' => $win,
            'loss' => $loss,
            'profit' => $profit,
            'winRate' => $total ? round($win / $total * 100, 2) : 0,
            'avgProfit' => $total ? round($profit / $total, 2) : 0,
            'maxDrawdown' => $maxDrawdown,
            'bestTrade' => $best,
            'worstTrade' => $worst,
            'maxWinStreak' => $maxStreak,
            'avgHoldTime' => $avgHold,
            'sharpeRatio' => $sharpe,
        ];
    }

    public function getOpenPosition($userId, $market, $symbol) {
        $key = "$userId|$market|$symbol";
        return $this->openPositions[$key] ?? null;
    }
    public function recordOpenPosition($userId, $market, $symbol, $side, $order) {
        $key = "$userId|$market|$symbol";
        $entry = $order['price'] ?? null;
        $size = $order['size'] ?? 0.01;
        $openedAt = now();
        $this->openPositions[$key] = [
            'side' => $side,
            'entry' => $entry,
            'size' => $size,
            'openedAt' => $openedAt,
            'order' => $order
        ];
        // Persist to DB
        DB::table('bot_trades')->insert([
            'user_id' => $userId,
            'market' => $market,
            'symbol' => $symbol,
            'side' => $side,
            'entry' => $entry,
            'size' => $size,
            'opened_at' => $openedAt,
            'entry_order' => json_encode($order),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
    public function closePosition($userId, $market, $symbol, $order, $exitPrice) {
        $key = "$userId|$market|$symbol";
        if (!isset($this->openPositions[$key])) return;
        $position = $this->openPositions[$key];
        $profit = ($exitPrice - $position['entry']) * ($position['side'] === 'buy' ? 1 : -1) * $position['size'];
        $closedAt = now();
        // Update DB
        DB::table('bot_trades')
            ->where('user_id', $userId)
            ->where('market', $market)
            ->where('symbol', $symbol)
            ->whereNull('closed_at')
            ->orderByDesc('opened_at')
            ->limit(1)
            ->update([
                'exit' => $exitPrice,
                'profit' => $profit,
                'closed_at' => $closedAt,
                'exit_order' => json_encode($order),
                'updated_at' => now()
            ]);
        unset($this->openPositions[$key]);
        return $profit;
    }

    // Calculate position size based on user risk and balance
    public function calculatePositionSize($userId, $market, $symbol, $riskLevel = 1, $stopLossPercent = 2) {
        // TODO: Fetch user balance from DB or API
        $balance = 1000; // Placeholder
        $riskPerc = min(max($riskLevel, 1), 5);
        $riskAmount = ($balance * $riskPerc) / 100;
        return $riskAmount / ($stopLossPercent / 100);
    }

    // Trailing stop logic
    public function isTrailingStopHit($entryPrice, $currentPrice, $trailingPercent, $highestSinceEntry) {
        $trailStop = $highestSinceEntry * (1 - $trailingPercent / 100);
        return $currentPrice <= $trailStop && $currentPrice > $entryPrice;
    }

    // Calculate Sharpe Ratio for trades
    public function calculateSharpeRatio($trades, $riskFreeRate = 0) {
        $returns = array_map(function($t) { return $t->profit ?? 0; }, $trades);
        $n = count($returns);
        if ($n < 2) return 0;
        $avg = array_sum($returns) / $n;
        $std = sqrt(array_sum(array_map(function($r) use ($avg) { return pow($r - $avg, 2); }, $returns)) / ($n - 1));
        if ($std == 0) return 0;
        return ($avg - $riskFreeRate) / $std;
    }

    // Before opening a new trade, check for open position
    public function canOpenTrade($userId, $market, $symbol) {
        $key = "$userId|$market|$symbol";
        return !isset($this->openPositions[$key]);
    }
}
