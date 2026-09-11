<?php

namespace App\Http\Controllers;

use App\Models\ScoreEntry;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ScoreEntryController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'team_id' => ['required', 'integer', 'exists:teams,id'],
            'game_id' => ['required', 'integer', 'exists:games,id'],
            'score' => ['required', 'integer'],
            'label' => ['nullable', 'string', 'max:255'],
        ]);

        $team = Team::query()->findOrFail($validated['team_id']);
        $label = filled($validated['label'] ?? null)
            ? $validated['label']
            : 'Q'.($team->scoreEntries()->where('game_id', $validated['game_id'])->count() + 1);

        ScoreEntry::query()->create([
            'team_id' => $team->id,
            'game_id' => $validated['game_id'],
            'score' => $validated['score'],
            'label' => $label,
            'scored_at' => now(),
        ]);

        return back();
    }

    public function update(Request $request, ScoreEntry $scoreEntry): RedirectResponse
    {
        $validated = $request->validate([
            'score' => ['sometimes', 'integer'],
            'label' => ['sometimes', 'string', 'max:255'],
        ]);

        if (array_key_exists('score', $validated)) {
            $validated['scored_at'] = now();
        }

        $scoreEntry->update($validated);

        return back();
    }

    public function destroy(ScoreEntry $scoreEntry): RedirectResponse
    {
        $scoreEntry->delete();

        return back();
    }
}
