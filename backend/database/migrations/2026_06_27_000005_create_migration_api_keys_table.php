<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('migration_api_keys', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('key')->unique();
            $table->unsignedBigInteger('migration_project_id');
            $table->json('permissions')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
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
        Schema::dropIfExists('migration_api_keys');
    }
};
