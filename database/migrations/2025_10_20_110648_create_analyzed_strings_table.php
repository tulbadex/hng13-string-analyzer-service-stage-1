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
        Schema::create('analyzed_strings', function (Blueprint $table) {
            $table->string('id')->primary(); // SHA-256 hash as primary key
            $table->text('value');
            $table->integer('length');
            $table->boolean('is_palindrome');
            $table->integer('unique_characters');
            $table->integer('word_count');
            $table->string('sha256_hash');
            $table->json('character_frequency_map');
            $table->timestamps();
            
            $table->index('is_palindrome');
            $table->index('length');
            $table->index('word_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analyzed_strings');
    }
};
