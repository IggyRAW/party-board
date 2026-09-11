<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Services\BoardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GameController extends Controller
{
    public function store(Request $request, BoardService $board): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $game = $board->createGame($validated['name']);

        return redirect()->route('board', [
            'tab' => 'score',
            'game' => $game->id,
        ]);
    }

    public function destroy(Game $game): RedirectResponse
    {
        $game->delete();

        return back();
    }
}
