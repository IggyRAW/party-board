<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prizes', function (Blueprint $table) {
            $table->boolean('is_finale')->default(false)->after('won_at');
        });

        Schema::create('board_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_settings');

        Schema::table('prizes', function (Blueprint $table) {
            $table->dropColumn('is_finale');
        });
    }
};
