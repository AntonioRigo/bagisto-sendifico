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
        Schema::create('sendifico_shipments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('order_id')->unique();
            $table->unsignedBigInteger('shipment_id')->nullable();
            $table->string('ext_id')->nullable();
            $table->string('status')->default('territoryPending');
            $table->boolean('is_paid')->default(false);
            $table->boolean('is_terminal')->default(false);
            $table->string('carrier_token')->nullable();
            $table->decimal('price_total', 12, 4)->nullable();
            $table->decimal('goods_collection', 12, 4)->default(0);
            $table->string('territory_base_id')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('tracking_carrier_url')->nullable();
            $table->unsignedInteger('incident_count')->nullable();
            $table->json('parcel')->nullable();
            $table->json('rates')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sendifico_shipments');
    }
};
