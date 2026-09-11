<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('score_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->integer('score')->default(0);
            $table->string('label');
            $table->timestamp('scored_at')->nullable();
            $table->timestamps();
        });

        Schema::create('prizes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamp('won_at')->nullable();
            $table->timestamps();
        });

        Schema::create('participants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('win_records', function (Blueprint $table) {
            $table->id();
            $table->string('prize_name');
            $table->string('winner_name');
            $table->timestamp('drawn_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('win_records');
        Schema::dropIfExists('participants');
        Schema::dropIfExists('prizes');
        Schema::dropIfExists('score_entries');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('games');
    }
};
