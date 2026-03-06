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
        Schema::create('aeat_fiscal_data_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('aeat_fiscal_data_request_id')
                ->constrained(table: 'aeat_fiscal_data_requests', indexName: 'afdf_request_fk')
                ->cascadeOnDelete();
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('filename');
            $table->string('sha256', 64);
            $table->unsignedBigInteger('bytes')->default(0);
            $table->unsignedInteger('line_count')->default(0);
            $table->unsignedInteger('record_count')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['aeat_fiscal_data_request_id', 'created_at'], 'afdf_request_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('aeat_fiscal_data_files');
    }
};