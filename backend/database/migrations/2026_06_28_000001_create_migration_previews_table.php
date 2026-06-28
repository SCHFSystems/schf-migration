<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('migration_previews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('migration_projects')->cascadeOnDelete();
            $table->string('status')->default('blocked');
            $table->boolean('ready_for_bundle')->default(false);
            $table->json('summary_json')->nullable();
            $table->json('entities_json')->nullable();
            $table->json('warnings_json')->nullable();
            $table->json('errors_json')->nullable();
            $table->json('ignored_json')->nullable();
            $table->json('historical_json')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index('project_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_previews');
    }
};
