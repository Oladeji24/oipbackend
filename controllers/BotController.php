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
        $result = $this->bot->startBot($userId, $market);
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
}
