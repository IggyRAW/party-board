<?php

namespace App\Http\Controllers;

use App\Models\Prize;
use App\Services\BoardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PrizeController extends Controller
{
    public function store(Request $request, BoardService $board): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $board->addNamedRecords('prize', [$validated['name']]);

        return back();
    }

    public function bulk(Request $request, BoardService $board): RedirectResponse
    {
        $validated = $request->validate([
            'names' => ['required', 'string'],
        ]);

        $names = $this->splitNames($validated['names']);

        if ($names === []) {
            throw ValidationException::withMessages([
                'names' => '景品名を1行以上入力してください。',
            ]);
        }

        $board->addNamedRecords('prize', $names);

        return back();
    }

    public function updateFinale(Request $request, Prize $prize, BoardService $board): RedirectResponse
    {
        $validated = $request->validate([
            'is_finale' => ['required', 'boolean'],
        ]);

        $board->setPrizeFinale($prize, $validated['is_finale']);

        return back();
    }

    public function destroy(Prize $prize): RedirectResponse
    {
        if ($prize->isWon()) {
            throw ValidationException::withMessages([
                'prize' => '当選済みの景品は削除できません。',
            ]);
        }

        $prize->delete();

        return back();
    }

    /**
     * @return list<string>
     */
    private function splitNames(string $raw): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $raw) ?: [])
            ->map(fn (string $name) => trim($name))
            ->filter()
            ->values()
            ->all();
    }
}
