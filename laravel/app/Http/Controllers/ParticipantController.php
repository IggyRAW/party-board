<?php

namespace App\Http\Controllers;

use App\Models\Participant;
use App\Services\BoardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ParticipantController extends Controller
{
    public function store(Request $request, BoardService $board): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $board->addNamedRecords('participant', [$validated['name']]);

        return back();
    }

    public function bulk(Request $request, BoardService $board): RedirectResponse
    {
        $validated = $request->validate([
            'names' => ['required', 'string'],
        ]);

        $names = collect(preg_split('/\r\n|\r|\n/', $validated['names']) ?: [])
            ->map(fn (string $name) => trim($name))
            ->filter()
            ->values()
            ->all();

        if ($names === []) {
            throw ValidationException::withMessages([
                'names' => '参加者名を1行以上入力してください。',
            ]);
        }

        $board->addNamedRecords('participant', $names);

        return back();
    }

    public function destroy(Participant $participant): RedirectResponse
    {
        $participant->delete();

        return back();
    }
}
