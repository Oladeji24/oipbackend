<?php
// BotController.php
// Controller for handling bot trading actions (start, stop, switch, status)

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Bot\BotLogic;

class BotController extends Controller
{
    protected $bot;

    public function __construct(BotLogic $bot)
    {
        $this->bot = $bot;
    }

    public function start(Request $request)
    {
        $userId = $request->user()->id ?? 1; // Placeholder for user auth
        $market = $request->input('market');
        $symbol = $request->input('symbol');
        $result = $this->bot->startBot($userId, $market, $symbol);
        return response()->json($result);
    }

    public function stop(Request $request)
    {
        $userId = $request->user()->id ?? 1;
        $result = $this->bot->stopBot($userId);
        return response()->json($result);
    }

    public function switch(Request $request)
    {
        $userId = $request->user()->id ?? 1;
        $newMarket = $request->input('market');
        $result = $this->bot->switchMarket($userId, $newMarket);
        return response()->json($result);
    }

    // Get bot analytics and performance stats
    public function analytics(Request $request)
    {
        $userId = $request->user()->id ?? 1;
        $result = $this->bot->getBotAnalytics($userId);
        return response()->json($result);
    }

    // Allow user to update bot parameters (EMA/RSI periods, risk)
    public function updateParams(Request $request)
    {
        $userId = $request->user()->id ?? 1;
        $params = $request->only(['strategy', 'emaFast', 'emaSlow', 'rsiPeriod', 'macdFast', 'macdSlow', 'macdSignal', 'riskLevel', 'tripleFast', 'tripleMid', 'tripleSlow']);
        $result = $this->bot->updateBotParams($userId, $params);
        return response()->json($result);
    }

    // Get current user bot parameters
    public function getParams(Request $request)
    {
        $userId = $request->user()->id ?? 1;
        $params = $this->bot->getUserParams($userId);
        return response()->json($params);
    }

    // Get advanced bot analytics
    public function advancedAnalytics(Request $request)
    {
        $userId = $request->user()->id ?? 1;
        $result = $this->bot->getBotAdvancedAnalytics($userId);
        return response()->json($result);
    }
}
