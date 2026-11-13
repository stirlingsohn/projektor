<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_list_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            // Per changed task: its state before and after, null where it did not exist.
            $table->json('tasks');
            $table->timestamp('undone_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'task_list_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_changes');
    }
};
