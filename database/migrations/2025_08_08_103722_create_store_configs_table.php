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
        Schema::create('store_configs', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_open')->default(false);
            $table->string('current_season')->default('Sin temporada');
            $table->date('season_start_date')->nullable();
            $table->date('season_end_date')->nullable();
            $table->datetime('store_opening_date')->nullable();
            $table->datetime('store_closing_date')->nullable();
            $table->decimal('max_order_amount', 10, 2)->default(10000.00);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_configs');
    }
};
