<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('geocoded_address')->nullable()->after('longitude');
            $table->string('geocoded_city')->nullable()->after('geocoded_address');
            $table->string('geocoded_country')->nullable()->after('geocoded_city');

            $table->index('geocoded_city');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['geocoded_city']);
            $table->dropColumn(['geocoded_address', 'geocoded_city', 'geocoded_country']);
        });
    }
};
