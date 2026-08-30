<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sendifico_territory_matches', function (Blueprint $table) {
            $table->increments('id');
            $table->string('country', 2);
            $table->string('province_norm');
            $table->string('city_norm');
            $table->string('territory_base_id');
            $table->string('source', 10)->default('auto');
            $table->timestamps();

            $table->unique(['country', 'province_norm', 'city_norm'], 'sendifico_territory_matches_key_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sendifico_territory_matches');
    }
};
