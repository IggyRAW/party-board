<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Services\BoardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function store(Request $request, BoardService $board): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:teams,name'],
        ]);

        $board->createTeam($validated['name']);

        return back();
    }

    public function update(Request $request, Team $team): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:teams,name,'.$team->id],
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
