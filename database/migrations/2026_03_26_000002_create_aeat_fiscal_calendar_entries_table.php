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
        Schema::create('aeat_fiscal_calendar_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('aeat_fiscal_calendar_id')
                ->constrained(table: 'aeat_fiscal_calendars', indexName: 'afce_calendar_fk')
                ->cascadeOnDelete();
            $table->string('model_code', 16);
            $table->string('model_name');
            $table->string('category', 64)->nullable();
            $table->string('period_label', 64);
            $table->dateTime('base_due_at');
            $table->dateTime('due_at');
            $table->string('status', 20)->default('pending');
            $table->dateTime('snoozed_until')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->string('source_label')->nullable();
            $table->string('source_url')->nullable();
            $table->timestamps();

            $table->unique(['aeat_fiscal_calendar_id', 'model_code', 'period_label'], 'afce_calendar_model_period_unique');
            $table->index(['aeat_fiscal_calendar_id', 'status'], 'afce_calendar_status_idx');
            $table->index(['aeat_fiscal_calendar_id', 'due_at'], 'afce_calendar_due_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('aeat_fiscal_calendar_entries');
    }
};
