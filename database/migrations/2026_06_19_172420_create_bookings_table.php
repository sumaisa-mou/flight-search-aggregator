<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('flight_id')->index();
            $table->string('carrier', 2);
            $table->string('flight_number');
            $table->string('origin', 3);
            $table->string('destination', 3);
            $table->dateTime('departure_at');
            $table->dateTime('arrival_at');
            $table->unsignedTinyInteger('stops');
            $table->unsignedInteger('price_amount');
            $table->char('price_currency', 3);
            $table->string('source');
            $table->json('passengers');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
