<?php

use App\Models\User;
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
        Schema::create('aeat_fiscal_calendars', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('taxpayer_nif', 16);
            $table->unsignedSmallInteger('exercise');
            $table->string('regime', 32)->default('mixto');
            $table->json('enabled_models');
            $table->boolean('is_default')->default(false);
            $table->timestamp('last_generated_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'taxpayer_nif', 'exercise'], 'afc_user_nif_exercise_unique');
            $table->index(['user_id', 'is_default'], 'afc_user_default_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('aeat_fiscal_calendars');
    }
};
