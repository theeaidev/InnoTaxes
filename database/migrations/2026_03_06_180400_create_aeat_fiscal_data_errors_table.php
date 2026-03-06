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
        Schema::create('aeat_fiscal_data_errors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('aeat_fiscal_data_request_id')
                ->constrained(table: 'aeat_fiscal_data_requests', indexName: 'afde_request_fk')
                ->cascadeOnDelete();
            $table->string('stage', 64);
            $table->string('code', 64)->nullable();
            $table->text('message');
            $table->json('details')->nullable();
            $table->boolean('retryable')->default(false);
            $table->unsignedInteger('attempt')->default(1);
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['aeat_fiscal_data_request_id', 'occurred_at'], 'afde_request_occurred_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('aeat_fiscal_data_errors');
    }
};