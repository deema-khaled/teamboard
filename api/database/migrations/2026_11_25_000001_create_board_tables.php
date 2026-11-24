<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('description', 500)->nullable();
            $table->string('color', 20)->default('indigo');
            $table->timestamps();
        });

        Schema::create('board_user', function (Blueprint $table) {
            $table->foreignId('board_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16)->default('member');
            $table->timestamps();

            $table->primary(['board_id', 'user_id']);
        });

        Schema::create('columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['board_id', 'position']);
        });

        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained()->cascadeOnDelete();
            $table->foreignId('column_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('label', 20)->nullable();
            $table->date('due_date')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();

            $table->index(['column_id', 'position']);
            $table->index('due_date');
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->string('email');
            // Only a hash is stored; the plain token is in the email link.
            $table->string('token_hash', 64)->unique();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['board_id', 'email']);
        });

        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40);
            $table->string('description');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['board_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['activities', 'invitations', 'comments', 'cards', 'columns', 'board_user', 'boards'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
