<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_settlements', function (Blueprint $table) {
            $table->id();
            $table->char('period', 7)->unique();
            $table->decimal('other_income', 12, 2)->default(0);
            $table->string('other_income_note')->nullable();
            $table->decimal('account_balance', 12, 2)->nullable();
            $table->decimal('extra_reserve', 12, 2)->default(0);
            $table->timestamp('closed_at')->nullable();
            $table->json('snapshot')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('installment', 12, 2);
            $table->char('from_period', 7);
            $table->char('to_period', 7)->nullable();
            $table->unsignedTinyInteger('due_day')->nullable();
            $table->timestamps();
        });

        Schema::create('tax_parameters', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->decimal('pit_rate_1', 5, 2)->default(12);
            $table->decimal('pit_rate_2', 5, 2)->default(32);
            $table->decimal('pit_threshold', 12, 2)->default(120000);
            $table->decimal('pit_reducing_amount', 12, 2)->default(3600);
            $table->decimal('zus_base', 12, 2);
            $table->decimal('zus_pension_rate', 5, 2)->default(19.52);
            $table->decimal('zus_disability_rate', 5, 2)->default(8);
            $table->decimal('zus_sickness_rate', 5, 2)->default(2.45);
            $table->decimal('zus_accident_rate', 5, 2)->default(1.67);
            $table->decimal('zus_labour_fund_rate', 5, 2)->default(2.45);
            $table->boolean('zus_sickness_enabled')->default(true);
            $table->decimal('health_rate', 5, 2)->default(9);
            $table->decimal('health_min_base', 12, 2);
            $table->timestamps();
        });

        Schema::create('ksef_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->date('date_from');
            $table->date('date_to');
            $table->string('subject_type', 20);
            $table->unsignedInteger('found')->default(0);
            $table->unsignedInteger('imported')->default(0);
            $table->string('status', 10)->default('ok');
            $table->text('error')->nullable();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('ksef_sync_logs');
        Schema::dropIfExists('tax_parameters');
        Schema::dropIfExists('loans');
        Schema::dropIfExists('monthly_settlements');
    }
};
