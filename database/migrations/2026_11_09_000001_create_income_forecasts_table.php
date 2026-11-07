<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Zapisane dane wejściowe modelu dochodu (prognozy) dla miesiąca. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('income_forecasts', function (Blueprint $table) {
            $table->id();
            $table->char('period', 7)->unique();
            $table->json('data');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('income_forecasts');
    }
};
