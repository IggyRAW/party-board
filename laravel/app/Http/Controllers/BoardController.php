<?php

namespace App\Http\Controllers;

use App\Support\BoardState;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BoardController extends Controller
{
    public function index(Request $request): Response
    {
        $selectedGameId = $request->filled('game') ? $request->integer('game') : null;
        $tab = (string) $request->query('tab', 'score');

        return Inertia::render('Board', BoardState::toArray($selectedGameId, $tab));
    }
}
