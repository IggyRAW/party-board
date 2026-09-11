<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Team;
use App\Services\BoardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function store(Request $request, BoardService $board): RedirectResponse
    {
        $validated = $request->validate([
            'game_id' => ['required', 'integer', 'exists:games,id'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $game = Game::query()->findOrFail($validated['game_id']);
        $board->createTeam($game, $validated['name']);

        return back();
    }

    public function update(Request $request, Team $team): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $team->update($validated);

        return back();
    }

    public function destroy(Team $team): RedirectResponse
    {
        $team->delete();

        return back();
    }
}
