<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contractor_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            // fragmenty nazwy rozdzielone średnikiem, np. "orlen;pkn orlen" - do automatycznego przypisania grupy
            $table->string('pattern')->nullable();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // cost | revenue
            $table->string('direction', 10)->default('cost');
            // cost: koszt podatkowy, income_deduction: odliczenie od dochodu (ZUS społeczne),
            // health: składka zdrowotna (na skali bez wpływu na PIT), none: poza rozliczeniem, revenue: przychód
            $table->string('treatment', 20)->default('cost');
            $table->decimal('pit_percent', 5, 2)->default(100);
            $table->decimal('vat_percent', 5, 2)->default(100);
            $table->string('group')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('contractors', function (Blueprint $table) {
            $table->id();
            $table->string('nip', 20)->nullable()->unique();
            $table->string('full_name');
            $table->string('short_name')->nullable();
            $table->foreignId('group_id')->nullable()->constrained('contractor_groups')->nullOnDelete();
            $table->foreignId('default_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->boolean('own_settlement_section')->default(false);
            $table->string('address')->nullable();
            $table->timestamps();
        });

        Schema::create('category_rules', function (Blueprint $table) {
            $table->id();
            // item_name | contractor_nip | contractor_name
            $table->string('field', 20);
            // equals | contains | regex
            $table->string('match', 10)->default('contains');
            $table->string('pattern');
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->integer('priority')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_rules');
        Schema::dropIfExists('contractors');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('contractor_groups');
    }
};
