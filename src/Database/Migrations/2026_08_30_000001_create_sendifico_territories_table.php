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
        Schema::create('sendifico_territories', function (Blueprint $table) {
            $table->increments('id');
            $table->string('territory_base_id')->unique();
            $table->string('country', 2)->index();
            $table->string('territory1_name');
            $table->string('territory2_name');
            $table->string('territory3_name')->nullable();
            $table->string('searchable_text');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sendifico_territories');
    }
};
