<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // Dokument ręczny w walucie obcej: kwoty w walucie, kurs średni NBP z dnia roboczego przed wystawieniem.
            // Kolumny net/vat/gross zawsze trzymają kwoty w PLN – to podstawa rozliczenia.
            $table->decimal('net_currency', 12, 2)->nullable()->after('currency');
            $table->decimal('vat_currency', 12, 2)->nullable()->after('net_currency');
            $table->decimal('gross_currency', 12, 2)->nullable()->after('vat_currency');
            $table->decimal('exchange_rate', 10, 4)->nullable()->after('gross_currency');
            $table->date('exchange_rate_date')->nullable()->after('exchange_rate');
            $table->string('exchange_rate_table', 32)->nullable()->after('exchange_rate_date');
            // Dokument skopiowany z innego miesiąca (koszty powtarzalne).
            $table->foreignId('copied_from_id')->nullable()->after('notes')->constrained('documents')->nullOnDelete();
        });

        Schema::table('document_items', function (Blueprint $table) {
            $table->decimal('net_currency', 12, 2)->nullable()->after('gross');
            $table->decimal('vat_currency', 12, 2)->nullable()->after('net_currency');
            $table->decimal('gross_currency', 12, 2)->nullable()->after('vat_currency');
        });
    }

    public function down(): void
    {
        Schema::table('document_items', function (Blueprint $table) {
            $table->dropColumn(['net_currency', 'vat_currency', 'gross_currency']);
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('copied_from_id');
            $table->dropColumn(['net_currency', 'vat_currency', 'gross_currency', 'exchange_rate', 'exchange_rate_date', 'exchange_rate_table']);
        });
    }
};
