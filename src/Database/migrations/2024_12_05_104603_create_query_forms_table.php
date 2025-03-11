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
        Schema::create('query_forms', function (Blueprint $table) {
            $table->id();
            $table->text('title')->nullable();
            $table->text('query_details')->nullable();
            $table->text('host')->nullable();
            $table->text('port')->nullable();
            $table->text('database')->nullable();
            $table->text('username')->nullable();
            $table->text('password')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('query_forms');
    }
};
