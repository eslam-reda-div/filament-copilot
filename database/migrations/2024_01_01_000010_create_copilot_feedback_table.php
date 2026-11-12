<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('copilot_feedback', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // One detailed report per rated reply; resubmitting updates it.
            $table->foreignUlid('message_id')
                ->unique()
                ->constrained('copilot_messages')
                ->cascadeOnDelete();
            $table->foreignUlid('conversation_id')
                ->constrained('copilot_conversations')
                ->cascadeOnDelete();

            $table->morphs('participant');
            $table->string('panel_id');
            $table->nullableMorphs('tenant');

            // What went wrong, in the user's words.
            $table->string('category');
            $table->text('expected_answer')->nullable();
            $table->boolean('suggests_new_tool')->default(false);

            // Admin review workflow.
            $table->string('status')->default('open');
            $table->text('admin_notes')->nullable();
            $table->nullableMorphs('reviewer');
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['panel_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('copilot_feedback');
    }
};
