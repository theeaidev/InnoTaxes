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
        Schema::create('aeat_fiscal_data_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('aeat_fiscal_data_request_id')
                ->constrained(table: 'aeat_fiscal_data_requests', indexName: 'afdrec_request_fk')
                ->cascadeOnDelete();
            $table->foreignId('aeat_fiscal_data_file_id')
                ->nullable()
                ->constrained(table: 'aeat_fiscal_data_files', indexName: 'afdrec_file_fk')
                ->nullOnDelete();
            $table->unsignedInteger('line_number');
            $table->string('record_type', 8);
            $table->string('record_code', 16)->nullable();
            $table->string('layout_key', 128)->nullable();
            $table->unsignedInteger('line_length')->default(0);
            $table->longText('raw_line');
            $table->json('normalized_data')->nullable();
            $table->json('parse_warnings')->nullable();
            $table->timestamps();

            $table->index(['aeat_fiscal_data_request_id', 'line_number'], 'afdrec_request_line_idx');
            $table->index(['aeat_fiscal_data_request_id', 'record_type'], 'afdrec_request_type_idx');
            $table->index(['aeat_fiscal_data_request_id', 'record_code'], 'afdrec_request_code_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('aeat_fiscal_data_records');
    }
};