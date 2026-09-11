<?php

namespace Database\Seeders;

use App\Services\BoardService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (\App\Models\Game::query()->exists()) {
            return;
        }

        $board = app(BoardService::class);

        $quiz = $board->createGame('クイズバトル');
        $bingo = $board->createGame('ビンゴ');

        unset($quiz, $bingo);

        $board->addNamedRecords('prize', [
            'Amazonギフト券 5,000円',
            'QUOカード 3,000円',
            'スターバックスカード 2,000円',
        ]);

        $board->addNamedRecords('participant', [
            '田中 太郎',
            '鈴木 花子',
            '佐藤 次郎',
            '山田 美咲',
            '伊藤 健一',
        ]);
    }
}
