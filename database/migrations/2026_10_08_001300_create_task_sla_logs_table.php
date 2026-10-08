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
        Schema::create('task_sla_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('sla_policy_id')->constrained('sla_policies')->cascadeOnDelete();
            $table->dateTime('response_due_at')->nullable();
            $table->dateTime('resolution_due_at')->nullable();
            $table->dateTime('response_at')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->boolean('response_breached')->default(false)->index();
            $table->boolean('resolution_breached')->default(false)->index();
            $table->unsignedInteger('response_breach_minutes')->default(0);
            $table->unsignedInteger('resolution_breach_minutes')->default(0);
            $table->dateTime('response_warning_sent_at')->nullable();
            $table->dateTime('response_breach_sent_at')->nullable();
            $table->dateTime('resolution_warning_sent_at')->nullable();
            $table->dateTime('resolution_breach_sent_at')->nullable();
            $table->timestamps();

            $table->index(['response_breached', 'resolution_breached']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_sla_logs');
    }
};
