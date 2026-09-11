<?php

namespace App\Support;

use App\Models\BoardSetting;
use App\Models\Game;
use App\Models\Participant;
use App\Models\Prize;
use App\Models\ScoreEntry;
use App\Models\Team;
use App\Models\WinRecord;

class BoardState
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(?int $selectedGameId = null, string $tab = 'score'): array
    {
        $games = Game::query()->orderBy('id')->get(['id', 'name']);

        if ($selectedGameId === null || ! $games->contains('id', $selectedGameId)) {
            $selectedGameId = $games->first()?->id;
        }

        return [
            'tab' => in_array($tab, ['score', 'roulette'], true) ? $tab : 'score',
            'selectedGameId' => $selectedGameId,
            'games' => $games,
            'teams' => Team::query()->orderBy('id')->get()->map(fn (Team $team) => [
                'id' => $team->id,
                'name' => $team->name,
            ]),
            'scoreEntries' => ScoreEntry::query()->orderBy('id')->get()->map(fn (ScoreEntry $entry) => [
                'id' => $entry->id,
                'teamId' => $entry->team_id,
                'gameId' => $entry->game_id,
                'score' => $entry->score,
                'label' => $entry->label,
                'timestamp' => $entry->scored_at?->timezone('Asia/Tokyo')->format('m/d H:i') ?? '',
            ]),
            'prizes' => Prize::query()->orderBy('id')->get()->map(fn (Prize $prize) => [
                'id' => $prize->id,
                'name' => $prize->name,
                'isWon' => $prize->isWon(),
                'isFinale' => $prize->is_finale,
            ]),
            'finalePrizeLimit' => BoardSetting::finalePrizeLimit(),
            'wonPrizeIds' => Prize::query()->whereNotNull('won_at')->pluck('id')->values(),
            'participants' => Participant::query()->orderBy('id')->get(['id', 'name']),
            'winHistory' => WinRecord::query()->orderBy('id')->get()->map(fn (WinRecord $record) => [
                'id' => $record->id,
                'prize' => $record->prize_name,
                'winner' => $record->winner_name,
                'timestamp' => $record->drawn_at->timezone('Asia/Tokyo')->format('m/d H:i'),
            ]),
        ];
    }
}
