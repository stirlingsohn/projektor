<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Access of a client to a project (with all its subprojects and lists) or to a single list.
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('task_list_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('level');
            $table->timestamps();

            $table->unique(['user_id', 'project_id']);
            $table->unique(['user_id', 'task_list_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
