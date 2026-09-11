<?php

use App\Http\Controllers\BoardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\ParticipantController;
use App\Http\Controllers\PrizeController;
use App\Http\Controllers\RouletteController;
use App\Http\Controllers\ScoreEntryController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

Route::get('/', [BoardController::class, 'index'])->name('board');

Route::post('/games', [GameController::class, 'store'])->name('games.store');
Route::delete('/games/{game}', [GameController::class, 'destroy'])->name('games.destroy');

Route::post('/teams', [TeamController::class, 'store'])->name('teams.store');
Route::patch('/teams/{team}', [TeamController::class, 'update'])->name('teams.update');
Route::delete('/teams/{team}', [TeamController::class, 'destroy'])->name('teams.destroy');

Route::post('/score-entries', [ScoreEntryController::class, 'store'])->name('score-entries.store');
Route::patch('/score-entries/{scoreEntry}', [ScoreEntryController::class, 'update'])->name('score-entries.update');
Route::delete('/score-entries/{scoreEntry}', [ScoreEntryController::class, 'destroy'])->name('score-entries.destroy');

Route::post('/prizes', [PrizeController::class, 'store'])->name('prizes.store');
Route::post('/prizes/bulk', [PrizeController::class, 'bulk'])->name('prizes.bulk');
Route::patch('/prizes/{prize}/finale', [PrizeController::class, 'updateFinale'])->name('prizes.finale');
Route::delete('/prizes/{prize}', [PrizeController::class, 'destroy'])->name('prizes.destroy');

Route::post('/participants', [ParticipantController::class, 'store'])->name('participants.store');
Route::post('/participants/bulk', [ParticipantController::class, 'bulk'])->name('participants.bulk');
Route::delete('/participants/{participant}', [ParticipantController::class, 'destroy'])->name('participants.destroy');

Route::post('/roulette/spin', [RouletteController::class, 'spin'])->name('roulette.spin');
Route::post('/roulette/reset', [RouletteController::class, 'reset'])->name('roulette.reset');
Route::patch('/roulette/settings', [RouletteController::class, 'updateSettings'])->name('roulette.settings');

Route::get('/export/scores', [ExportController::class, 'scores'])->name('export.scores');
Route::get('/export/roulette', [ExportController::class, 'roulette'])->name('export.roulette');
