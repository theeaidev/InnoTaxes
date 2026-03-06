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
        Schema::create('aeat_certificate_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(\App\Models\User::class)->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('certificate_format', 12)->default('p12');
            $table->string('certificate_disk')->default('local');
            $table->string('certificate_path');
            $table->string('certificate_original_name')->nullable();
            $table->string('private_key_disk')->nullable();
            $table->string('private_key_path')->nullable();
            $table->string('private_key_original_name')->nullable();
            $table->text('passphrase')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('aeat_certificate_profiles');
    }
};
