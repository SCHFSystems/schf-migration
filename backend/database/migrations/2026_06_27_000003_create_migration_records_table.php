<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('migration_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('import_id');
            $table->string('source_table');
            $table->string('source_id')->nullable();
            $table->string('target_table')->nullable();
            $table->string('target_id')->nullable();
            $table->string('action');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('validation_errors')->nullable();
            $table->json('ai_suggestions')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->foreign('import_id')
                ->references('id')
                ->on('migration_imports')
                ->onDelete('cascade');

            $table->index('import_id');
            $table->index('source_table');
            $table->index('target_table');
            $table->index('action');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_records');
    }
};
