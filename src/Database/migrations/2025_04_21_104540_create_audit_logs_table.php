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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('user_id')->nullable();  // Store the ID of the user performing the action
            $table->string('ip_address')->nullable();  // Store the ip_address of the user performing the action
            $table->string('user_agent')->nullable();  // Store the user_agent of the user performing the action
            $table->string('action')->nullable();               // Store the action (created, updated, deleted)
            $table->string('model')->nullable();                // Store the model type (User, Post, etc.)
            $table->unsignedBigInteger('model_id')->nullable(); // Store the model ID (specific record affected)
            $table->text('old_values')->nullable();                // Store the model type (User, Post, etc.)
            $table->text('new_values')->nullable();                // Store the model type (User, Post, etc.)
            $table->text('database')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
