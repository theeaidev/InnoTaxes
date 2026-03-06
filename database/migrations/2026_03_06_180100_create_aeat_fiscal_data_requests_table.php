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
        Schema::create('aeat_fiscal_data_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(\App\Models\User::class)->constrained()->cascadeOnDelete();
            $table->foreignId('certificate_profile_id')
                ->nullable()
                ->constrained(table: 'aeat_certificate_profiles', indexName: 'afdr_certificate_profile_fk')
                ->nullOnDelete();
            $table->foreignId('precheck_certificate_profile_id')
                ->nullable()
                ->constrained(table: 'aeat_certificate_profiles', indexName: 'afdr_precheck_certificate_fk')
                ->nullOnDelete();
            $table->string('status', 32)->default('queued');
            $table->string('stage', 64)->nullable();
            $table->string('auth_method', 32);
            $table->string('taxpayer_nif', 16);
            $table->string('auth_nif', 16)->nullable();
            $table->boolean('pdp')->default(true);
            $table->string('domicile_status', 32)->default('unknown');
            $table->json('payload')->nullable();
            $table->longText('session_state')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->string('last_error_code', 64)->nullable();
            $table->text('last_error_message')->nullable();
            $table->json('last_error_context')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('awaiting_pin_at')->nullable();
            $table->timestamp('last_checked_domicile_at')->nullable();
            $table->timestamp('downloaded_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'taxpayer_nif']);
            $table->index(['auth_method', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('aeat_fiscal_data_requests');
    }
};