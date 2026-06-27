<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('migration_projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('source_type');
            $table->json('source_config')->nullable();
            $table->string('status')->default('draft');
            $table->json('ai_config')->nullable();
            $table->unsignedBigInteger('api_key_id')->nullable();
            $table->date('data_cutoff_date')->nullable();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('source_type');
            $table->index('organization_id');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_projects');
    }
};
