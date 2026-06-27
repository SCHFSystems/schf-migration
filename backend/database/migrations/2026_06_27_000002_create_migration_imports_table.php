<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('migration_imports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('migration_project_id');
            $table->integer('batch_number');
            $table->string('table_name');
            $table->integer('records_total')->default(0);
            $table->integer('records_imported')->default(0);
            $table->integer('records_skipped')->default(0);
            $table->integer('records_failed')->default(0);
            $table->string('status')->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('error_log')->nullable();
            $table->string('checksum')->nullable();
            $table->timestamps();

            $table->foreign('migration_project_id')
                ->references('id')
                ->on('migration_projects')
                ->onDelete('cascade');

            $table->index('migration_project_id');
            $table->index('status');
            $table->index('table_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_imports');
    }
};
