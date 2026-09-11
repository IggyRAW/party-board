<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Participant;
use App\Models\Prize;
use App\Models\ScoreEntry;
use App\Models\Team;
use App\Models\WinRecord;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function scores(Request $request): StreamedResponse
    {
        $filterGame = $request->query('filter', 'all');
        $games = Game::query()->orderBy('id')->get();
        $teams = Team::query()->orderBy('id')->get();
        $entries = ScoreEntry::query()->orderBy('id')->get();

        $ranking = $this->rankingLines($filterGame, $games, $teams, $entries);

        $lines = [
            '=== スコア集計エクスポート ===',
            '出力日時: '.now()->timezone('Asia/Tokyo')->format('Y/m/d H:i:s'),
            '',
            '── ランキング ──',
            ...$ranking,
            '',
        ];

        foreach ($games as $game) {
            $lines[] = "── {$game->name} ──";

            foreach ($teams as $team) {
                $lines[] = "  {$team->name}";
                $teamEntries = $entries->where('team_id', $team->id)->where('game_id', $game->id);

                foreach ($teamEntries as $entry) {
                    $lines[] = '    '.$entry->label.'  '.number_format($entry->score).'点';
                }
            }

            $lines[] = '';
        }

        return $this->download(implode("\n", $lines), 'scores.txt');
    }

    public function roulette(): StreamedResponse
    {
        $history = WinRecord::query()->orderBy('id')->get();
        $prizes = Prize::query()->orderBy('id')->get();
        $participants = Participant::query()->orderBy('id')->get();

        $historyLines = $history->isEmpty()
            ? ['(当選履歴なし)']
            : $history->map(function (WinRecord $record) {
                $stamp = $record->drawn_at->timezone('Asia/Tokyo')->format('m/d H:i');

                return "[{$stamp}]  {$record->prize_name}  →  {$record->winner_name}";
            })->all();

        $prizeLines = $prizes->values()->map(function (Prize $prize, int $index) {
            $won = $prize->isWon() ? '（当選済み）' : '';

            return ($index + 1).'. '.$prize->name.$won;
        })->all();

        $participantLines = $participants->values()->map(
            fn (Participant $participant, int $index) => ($index + 1).'. '.$participant->name
        )->all();

        $lines = [
            '=== 抽選結果 エクスポート ===',
            '出力日時: '.now()->timezone('Asia/Tokyo')->format('Y/m/d H:i:s'),
            '',
            '── 当選履歴 ──',
            ...$historyLines,
            '',
            '── 景品一覧 ──',
            ...$prizeLines,
            '',
            '── 参加者一覧 ──',
            ...$participantLines,
        ];

        return $this->download(implode("\n", $lines), 'roulette.txt');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Game>  $games
     * @param  \Illuminate\Support\Collection<int, Team>  $teams
     * @param  \Illuminate\Support\Collection<int, ScoreEntry>  $entries
     * @return list<string>
     */
    private function rankingLines(string $filterGame, $games, $teams, $entries): array
    {
        if ($filterGame === 'all') {
            $nameMap = [];

            foreach ($teams as $team) {
                $total = $entries->where('team_id', $team->id)->sum('score');
                $nameMap[$team->name] = ($nameMap[$team->name] ?? 0) + $total;
            }

            arsort($nameMap);

            $rank = 1;
            $lines = [];

            foreach ($nameMap as $name => $total) {
                $lines[] = $rank.'位  '.$name.'  合計: '.number_format($total).'点';
                $rank++;
            }

            return $lines;
        }

        $gameId = (int) $filterGame;
        $game = $games->firstWhere('id', $gameId);
        $relevant = $teams
            ->map(function (Team $team) use ($entries, $game, $gameId) {
                return [
                    'name' => $team->name,
                    'game' => $game?->name ?? '',
                    'total' => $entries->where('team_id', $team->id)->where('game_id', $gameId)->sum('score'),
                ];
            })
            ->sortByDesc('total')
            ->values();

        return $relevant->values()->map(function (array $row, int $index) {
            return ($index + 1).'位  ['.$row['game'].'] '.$row['name'].'  合計: '.number_format($row['total']).'点';
        })->all();
    }

    private function download(string $content, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, $filename, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
