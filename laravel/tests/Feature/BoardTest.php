<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Participant;
use App\Models\Prize;
use App\Models\ScoreEntry;
use App\Models\Team;
use App\Models\WinRecord;
use App\Services\BoardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BoardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession(['board_authenticated' => true]);
    }

    public function test_board_page_renders(): void
    {
        $this->assertSame('sqlite', config('database.default'));

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Board'));
    }

    public function test_board_partial_reload_returns_live_data_props(): void
    {
        $this->post('/games', ['name' => 'クイズ']);
        $entry = ScoreEntry::query()->firstOrFail();
        $this->patch('/score-entries/'.$entry->id, ['score' => 250]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Board')
                ->reloadOnly([
                    'games',
                    'teams',
                    'scoreEntries',
                    'prizes',
                    'wonPrizeIds',
                    'finalePrizeLimit',
                    'participants',
                    'winHistory',
                ], function (Assert $reload) use ($entry) {
                    $reload->where(
                        'scoreEntries',
                        fn (mixed $entries) => collect($entries)->contains(
                            fn (array $item) => $item['id'] === $entry->id && $item['score'] === 250
                        )
                    );
                }));
    }

    public function test_creating_a_game_seeds_default_teams_and_entries(): void
    {
        $this->post('/games', ['name' => 'クイズバトル'])
            ->assertRedirect();

        $game = Game::query()->firstOrFail();
        $this->assertSame('クイズバトル', $game->name);
        $this->assertSame(5, Team::query()->count());
        $this->assertSame(50, ScoreEntry::query()->count());
        $this->assertTrue(Team::query()->where('name', 'チームA')->exists());
        $this->assertTrue(
            ScoreEntry::query()->where('game_id', $game->id)->where('team_id', Team::query()->firstOrFail()->id)->exists()
        );
    }

    public function test_creating_another_game_reuses_shared_teams(): void
    {
        $this->post('/games', ['name' => 'クイズバトル'])->assertRedirect();
        $this->post('/games', ['name' => 'ビンゴ'])->assertRedirect();

        $this->assertSame(5, Team::query()->count());
        $this->assertSame(100, ScoreEntry::query()->count());
        $this->assertSame(2, Game::query()->count());
        $this->assertSame(
            50,
            ScoreEntry::query()->where('game_id', Game::query()->where('name', 'ビンゴ')->value('id'))->count()
        );
    }

    public function test_creating_a_team_seeds_entries_for_every_game(): void
    {
        $this->post('/games', ['name' => 'クイズバトル'])->assertRedirect();
        $this->post('/games', ['name' => 'ビンゴ'])->assertRedirect();

        $this->post('/teams', ['name' => 'チームF'])->assertRedirect();

        $team = Team::query()->where('name', 'チームF')->firstOrFail();
        $this->assertSame(6, Team::query()->count());
        $this->assertSame(20, $team->scoreEntries()->count());
        $this->assertSame(2, $team->scoreEntries()->pluck('game_id')->unique()->count());
    }

    public function test_team_score_can_be_updated(): void
    {
        $this->post('/games', ['name' => 'ビンゴ']);
        $team = Team::query()->firstOrFail();
        $entry = $team->scoreEntries()->firstOrFail();

        $this->patch('/score-entries/'.$entry->id, ['score' => 120])
            ->assertRedirect();

        $this->assertSame(120, $entry->fresh()->score);
        $this->assertNotNull($entry->fresh()->scored_at);
    }

    public function test_team_scores_are_scoped_to_each_game(): void
    {
        $this->post('/games', ['name' => 'クイズ'])->assertRedirect();
        $this->post('/games', ['name' => 'ビンゴ'])->assertRedirect();

        $team = Team::query()->where('name', 'チームA')->firstOrFail();
        $quiz = Game::query()->where('name', 'クイズ')->firstOrFail();
        $bingo = Game::query()->where('name', 'ビンゴ')->firstOrFail();
        $quizEntry = $team->scoreEntries()->where('game_id', $quiz->id)->firstOrFail();

        $this->patch('/score-entries/'.$quizEntry->id, ['score' => 80])->assertRedirect();

        $this->assertSame(80, $quizEntry->fresh()->score);
        $this->assertSame(0, (int) $team->scoreEntries()->where('game_id', $bingo->id)->sum('score'));
    }

    public function test_deleting_a_game_keeps_shared_teams(): void
    {
        $this->post('/games', ['name' => '残すゲーム'])->assertRedirect();
        $this->post('/games', ['name' => '削除対象'])->assertRedirect();

        $game = Game::query()->where('name', '削除対象')->firstOrFail();

        $this->delete('/games/'.$game->id)->assertRedirect();

        $this->assertDatabaseCount('games', 1);
        $this->assertDatabaseCount('teams', 5);
        $this->assertDatabaseCount('score_entries', 50);
        $this->assertSame(
            50,
            ScoreEntry::query()->where('game_id', Game::query()->where('name', '残すゲーム')->value('id'))->count()
        );
    }

    public function test_roulette_spin_marks_prize_and_removes_participant(): void
    {
        $board = app(BoardService::class);
        $board->addNamedRecords('prize', ['Amazonギフト券']);
        $board->addNamedRecords('participant', ['田中 太郎']);

        $prize = Prize::query()->firstOrFail();

        $this->post('/roulette/spin', ['prize_id' => $prize->id])
            ->assertRedirect();

        $this->assertNotNull($prize->fresh()->won_at);
        $this->assertDatabaseCount('participants', 0);
        $this->assertDatabaseCount('win_records', 1);
        $this->assertSame('田中 太郎', WinRecord::query()->firstOrFail()->winner_name);
    }

    public function test_roulette_reset_restores_winners_as_participants(): void
    {
        $board = app(BoardService::class);
        $board->addNamedRecords('prize', ['QUOカード']);
        $board->addNamedRecords('participant', ['鈴木 花子']);

        $prize = Prize::query()->firstOrFail();
        $this->post('/roulette/spin', ['prize_id' => $prize->id]);
        $this->post('/roulette/reset')->assertRedirect();

        $this->assertNull($prize->fresh()->won_at);
        $this->assertDatabaseCount('win_records', 0);
        $this->assertTrue(Participant::query()->where('name', '鈴木 花子')->exists());
    }

    public function test_bulk_prizes_are_created_from_newlines(): void
    {
        $this->post('/prizes/bulk', [
            'names' => "景品A\n景品B\n\n景品C",
        ])->assertRedirect();

        $this->assertDatabaseCount('prizes', 3);
    }

    public function test_finale_prizes_cannot_be_drawn_while_regular_prizes_remain(): void
    {
        $board = app(BoardService::class);
        $board->addNamedRecords('prize', ['通常景品', '上位景品']);
        $board->addNamedRecords('participant', ['田中 太郎', '鈴木 花子']);

        $regular = Prize::query()->where('name', '通常景品')->firstOrFail();
        $finale = Prize::query()->where('name', '上位景品')->firstOrFail();

        $this->patch('/prizes/'.$finale->id.'/finale', ['is_finale' => true])
            ->assertRedirect();
        $this->assertTrue($finale->fresh()->is_finale);

        $this->post('/roulette/spin', ['prize_id' => $finale->id])
            ->assertSessionHasErrors('prize_id');
        $this->assertNull($finale->fresh()->won_at);

        $this->post('/roulette/spin', ['prize_id' => $regular->id])
            ->assertRedirect();
        $this->assertNotNull($regular->fresh()->won_at);

        $this->post('/roulette/spin', ['prize_id' => $finale->id])
            ->assertRedirect();
        $this->assertNotNull($finale->fresh()->won_at);
    }

    public function test_finale_prize_limit_can_be_changed_and_trims_excess(): void
    {
        $board = app(BoardService::class);
        $board->addNamedRecords('prize', ['A', 'B', 'C']);

        foreach (Prize::query()->orderBy('id')->get() as $prize) {
            $this->patch('/prizes/'.$prize->id.'/finale', ['is_finale' => true])
                ->assertRedirect();
        }

        $this->assertSame(3, Prize::query()->where('is_finale', true)->count());

        $this->patch('/roulette/settings', ['finale_prize_limit' => 1])
            ->assertRedirect();

        $this->assertSame(1, Prize::query()->where('is_finale', true)->count());
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Board')
                ->where('finalePrizeLimit', 1));
    }

    public function test_finale_prize_marking_is_capped_by_limit(): void
    {
        $board = app(BoardService::class);
        $board->addNamedRecords('prize', ['A', 'B']);
        $this->patch('/roulette/settings', ['finale_prize_limit' => 1])->assertRedirect();

        $prizes = Prize::query()->orderBy('id')->get();
        $this->patch('/prizes/'.$prizes[0]->id.'/finale', ['is_finale' => true])->assertRedirect();
        $this->patch('/prizes/'.$prizes[1]->id.'/finale', ['is_finale' => true])
            ->assertSessionHasErrors('is_finale');
        $this->assertFalse($prizes[1]->fresh()->is_finale);
    }
}
