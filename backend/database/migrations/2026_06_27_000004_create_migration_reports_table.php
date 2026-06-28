<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('migration_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('migration_project_id');
            $table->json('summary')->nullable();
            $table->json('totals')->nullable();
            $table->float('duration_seconds')->nullable();
            $table->string('core_version')->nullable();
            $table->string('migration_version')->nullable();
            $table->string('legacy_version')->nullable();
            $table->string('package_hash')->nullable();
            $table->unsignedBigInteger('operator_id')->nullable();
            $table->string('backup_path')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();

            $table->foreign('migration_project_id')
                ->references('id')
                ->on('migration_projects')
                ->onDelete('cascade');

            $table->index('migration_project_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_reports');
    }
};
