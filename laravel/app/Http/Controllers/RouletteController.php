<?php

namespace App\Http\Controllers;

use App\Models\Prize;
use App\Services\BoardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RouletteController extends Controller
{
    public function spin(Request $request, BoardService $board): RedirectResponse
    {
        $validated = $request->validate([
            'prize_id' => ['required', 'integer', 'exists:prizes,id'],
        ]);

        $prize = Prize::query()->findOrFail($validated['prize_id']);
        $board->spin($prize);

        return back();
    }

    public function reset(BoardService $board): RedirectResponse
    {
        $board->resetRoulette();

        return back();
    }

    public function updateSettings(Request $request, BoardService $board): RedirectResponse
    {
        $validated = $request->validate([
            'finale_prize_limit' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        $board->setFinalePrizeLimit($validated['finale_prize_limit']);

        return back();
    }
}
