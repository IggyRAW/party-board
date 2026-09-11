<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('score_entries', function (Blueprint $table) {
            $table->unsignedBigInteger('game_id')->nullable()->after('team_id');
        });

        foreach (DB::table('teams')->orderBy('id')->get() as $team) {
            DB::table('score_entries')
                ->where('team_id', $team->id)
                ->update(['game_id' => $team->game_id]);
        }

        $canonicalByName = [];

        foreach (DB::table('teams')->orderBy('id')->get() as $team) {
            if (! isset($canonicalByName[$team->name])) {
                $canonicalByName[$team->name] = $team->id;

                continue;
            }

            DB::table('score_entries')
                ->where('team_id', $team->id)
                ->update(['team_id' => $canonicalByName[$team->name]]);

            DB::table('teams')->where('id', $team->id)->delete();
        }

        Schema::table('score_entries', function (Blueprint $table) {
            $table->foreign('game_id')->references('id')->on('games')->cascadeOnDelete();
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropForeign(['game_id']);
            $table->dropColumn('game_id');
            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->unsignedBigInteger('game_id')->nullable();
        });

        $firstGameId = DB::table('games')->orderBy('id')->value('id');

        foreach (DB::table('teams')->orderBy('id')->get() as $team) {
            $gameIds = DB::table('score_entries')
                ->where('team_id', $team->id)
                ->distinct()
                ->orderBy('game_id')
                ->pluck('game_id');

            if ($gameIds->isEmpty()) {
                DB::table('teams')->where('id', $team->id)->update(['game_id' => $firstGameId]);

                continue;
            }

            $primaryGameId = $gameIds->first();
            DB::table('teams')->where('id', $team->id)->update(['game_id' => $primaryGameId]);

            foreach ($gameIds->skip(1) as $gameId) {
                $newTeamId = DB::table('teams')->insertGetId([
                    'game_id' => $gameId,
                    'name' => $team->name,
                    'created_at' => $team->created_at,
                    'updated_at' => $team->updated_at,
                ]);

                DB::table('score_entries')
                    ->where('team_id', $team->id)
                    ->where('game_id', $gameId)
                    ->update(['team_id' => $newTeamId]);
            }
        }

        Schema::table('teams', function (Blueprint $table) {
            $table->foreign('game_id')->references('id')->on('games')->cascadeOnDelete();
        });

        Schema::table('score_entries', function (Blueprint $table) {
            $table->dropForeign(['game_id']);
            $table->dropColumn('game_id');
        });
    }
};
