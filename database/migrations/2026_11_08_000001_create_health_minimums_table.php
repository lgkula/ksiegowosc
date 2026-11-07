<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Minimalna podstawa składki zdrowotnej zmienia się niezależnie od roku podatkowego
 * (np. od lutego do stycznia), więc ma własną tabelę z miesiącem obowiązywania.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_minimums', function (Blueprint $table) {
            $table->id();
            // pierwszy miesiąc obowiązywania (RRRR-MM); obowiązuje do miesiąca przed kolejnym wpisem
            $table->char('valid_from', 7)->unique();
            $table->decimal('base', 12, 2);
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::table('tax_parameters', function (Blueprint $table) {
            $table->dropColumn('health_min_base');
        });
    }

    public function down(): void
    {
        Schema::table('tax_parameters', function (Blueprint $table) {
            $table->decimal('health_min_base', 12, 2)->default(0);
        });

        Schema::dropIfExists('health_minimums');
    }
};
