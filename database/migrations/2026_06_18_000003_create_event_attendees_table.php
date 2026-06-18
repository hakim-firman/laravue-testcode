<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_attendees', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id');
            $table->string('name');
            $table->string('email');
            $table->string('status')->default('going'); // 'interested' | 'going'
            $table->timestamp('reminded_3day_at')->nullable();
            $table->timestamp('reminded_24h_at')->nullable();
            $table->timestamps();

            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
            // One registration per email per event; re-registering upserts.
            $table->unique(['event_id', 'email']);
            $table->index('event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_attendees');
    }
};
