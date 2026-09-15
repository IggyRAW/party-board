<?php

namespace App\Services;

use App\Models\BoardSetting;
use App\Models\Game;
use App\Models\Participant;
use App\Models\Prize;
use App\Models\ScoreEntry;
use App\Models\Team;
use App\Models\WinRecord;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BoardService
{
    public const DEFAULT_TEAM_NAMES = ['チームA', 'チームB', 'チームC', 'チームD', 'チームE'];

    public const DEFAULT_ENTRY_COUNT = 10;

    public function createGame(string $name): Game
    {
        return DB::transaction(function () use ($name) {
            $game = Game::query()->create(['name' => $name]);
            $teams = Team::query()->orderBy('id')->get();

            if ($teams->isEmpty()) {
                foreach (self::DEFAULT_TEAM_NAMES as $teamName) {
                    $this->createTeam($teamName);
                }
            } else {
                foreach ($teams as $team) {
                    $this->seedDefaultEntries($team, $game);
                }
            }

            return $game->refresh();
        });
    }

    public function createTeam(string $name): Team
    {
        return DB::transaction(function () use ($name) {
            $team = Team::query()->create(['name' => $name]);

            foreach (Game::query()->orderBy('id')->get() as $game) {
                $this->seedDefaultEntries($team, $game);
            }

            return $team;
        });
    }

    public function seedDefaultEntries(Team $team, Game $game): void
    {
        $now = now();
        $rows = [];

        for ($i = 1; $i <= self::DEFAULT_ENTRY_COUNT; $i++) {
            $rows[] = [
                'team_id' => $team->id,
                'game_id' => $game->id,
                'score' => 0,
                'label' => 'Q'.$i,
                'scored_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        ScoreEntry::query()->insert($rows);
    }

    /**
     * @param  list<string>  $names
     */
    public function addNamedRecords(string $type, array $names): void
    {
        $now = now();
        $rows = array_map(fn (string $name) => [
            'name' => $name,
            'created_at' => $now,
            'updated_at' => $now,
        ], $names);

        if ($type === 'prize') {
            Prize::query()->insert($rows);

            return;
        }

        Participant::query()->insert($rows);
    }

    /**
     * 現在抽選してよい未当選景品。
     * 残り参加者が閾値より多い間は、運営指定の上位景品を除外する。
     * 閾値以下になったら、残っている景品すべてからランダムに当たる。
     *
     * @return Collection<int, Prize>
     */
    public function drawablePrizes(): Collection
    {
        $available = Prize::query()->available()->orderBy('id')->get();
        $remainingParticipants = Participant::query()->count();
        $holdUntil = BoardSetting::finaleHoldUntilRemaining();

        if ($remainingParticipants > $holdUntil) {
            $regular = $available->where('is_finale', false)->values();

            return $regular->isNotEmpty() ? $regular : $available->values();
        }

        return $available->values();
    }

    public function setFinalePrizeLimit(int $limit): void
    {
        BoardSetting::setValue(BoardSetting::FINALE_PRIZE_LIMIT, (string) $limit);

        $marked = Prize::query()
            ->where('is_finale', true)
            ->orderBy('id')
            ->get();

        if ($marked->count() <= $limit) {
            return;
        }

        Prize::query()
            ->whereIn('id', $marked->slice($limit)->pluck('id'))
            ->update(['is_finale' => false]);
    }

    public function setPrizeFinale(Prize $prize, bool $isFinale): void
    {
        if ($prize->isWon()) {
            throw ValidationException::withMessages([
                'prize' => '当選済みの景品は変更できません。',
            ]);
        }

        if ($isFinale && ! $prize->is_finale) {
            $limit = BoardSetting::finalePrizeLimit();
            $marked = Prize::query()->where('is_finale', true)->count();

            if ($marked >= $limit) {
                throw ValidationException::withMessages([
                    'is_finale' => "上位景品は{$limit}件までです。",
                ]);
            }
        }

        $prize->update(['is_finale' => $isFinale]);
    }

    public function spin(Prize $prize): WinRecord
    {
        if ($prize->isWon()) {
            throw ValidationException::withMessages([
                'prize_id' => 'この景品はすでに当選済みです。',
            ]);
        }

        $drawableIds = $this->drawablePrizes()->pluck('id');

        if (! $drawableIds->contains($prize->id)) {
            throw ValidationException::withMessages([
                'prize_id' => 'この景品は、残り参加者が'.BoardSetting::finaleHoldUntilRemaining().'人以下になるまで抽選できません。',
            ]);
        }

        return DB::transaction(function () use ($prize) {
            $participant = Participant::query()->inRandomOrder()->lockForUpdate()->first();

            if ($participant === null) {
                throw ValidationException::withMessages([
                    'participant' => '参加者がいません。',
                ]);
            }

            $prize->update(['won_at' => now()]);

            $record = WinRecord::query()->create([
                'prize_name' => $prize->name,
                'winner_name' => $participant->name,
                'drawn_at' => now(),
            ]);

            $participant->delete();

            return $record;
        });
    }

    public function resetRoulette(): void
    {
        DB::transaction(function () {
            $history = WinRecord::query()->orderBy('id')->get();
            $now = now();

            if ($history->isNotEmpty()) {
                Participant::query()->insert(
                    $history->map(fn (WinRecord $record) => [
                        'name' => $record->winner_name,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all()
                );
            }

            Prize::query()->whereNotNull('won_at')->update(['won_at' => null]);
            WinRecord::query()->delete();
        });
    }
}
