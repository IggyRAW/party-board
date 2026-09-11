<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        if ($request->session()->get('board_authenticated') === true) {
            return redirect()->route('board');
        }

        return Inertia::render('Login');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'login_id' => ['required', 'string'],
            'password' => ['required', 'string'],
            'website' => ['nullable', 'string'],
        ]);

        $honeypotFilled = filled($validated['website'] ?? null);
        $idMatches = hash_equals(config('board.login_id'), $validated['login_id']);
        $passwordMatches = hash_equals(config('board.login_password'), $validated['password']);

        if ($honeypotFilled || ! $idMatches || ! $passwordMatches) {
            throw ValidationException::withMessages([
                'login_id' => 'IDまたはパスワードが正しくありません。',
            ]);
        }

        $request->session()->regenerate();
        $request->session()->put('board_authenticated', true);

        return redirect()->intended(route('board'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget('board_authenticated');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
