<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('migration_project_id');
            $table->string('provider');
            $table->text('api_key_encrypted');
            $table->string('model')->nullable();
            $table->float('temperature')->default(0.3);
            $table->integer('max_tokens')->default(2000);
            $table->text('system_prompt')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('migration_project_id')
                ->references('id')
                ->on('migration_projects')
                ->onDelete('cascade');

            $table->index('migration_project_id');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_configs');
    }
};
