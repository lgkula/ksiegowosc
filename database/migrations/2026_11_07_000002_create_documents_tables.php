<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            // cost | revenue
            $table->string('direction', 10);
            // ksef | manual | issued
            $table->string('source', 10)->default('manual');
            // invoice | transfer | other
            $table->string('kind', 10)->default('invoice');
            $table->string('ksef_number', 64)->nullable()->unique();
            $table->string('number')->nullable();
            $table->date('issue_date');
            $table->date('sale_date')->nullable();
            // miesiąc rozliczenia w formacie RRRR-MM
            $table->char('period', 7)->index();
            $table->foreignId('contractor_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('net', 12, 2)->default(0);
            $table->decimal('vat', 12, 2)->default(0);
            $table->decimal('gross', 12, 2)->default(0);
            $table->string('currency', 3)->default('PLN');
            // null = do decyzji, true = ujęty w rozliczeniu, false = pominięty
            $table->boolean('included')->nullable();
            $table->longText('xml')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('document_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(1);
            $table->string('name');
            $table->decimal('qty', 12, 3)->default(1);
            $table->string('unit', 20)->nullable();
            $table->decimal('unit_net', 12, 4)->nullable();
            $table->decimal('net', 12, 2)->default(0);
            // stawka jako tekst: "23", "8", "5", "0", "zw", "np"
            $table->string('vat_rate', 5)->default('23');
            $table->decimal('vat', 12, 2)->default(0);
            $table->decimal('gross', 12, 2)->default(0);
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            // auto | manual
            $table->string('category_source', 10)->default('auto');
            $table->timestamps();
        });

        Schema::create('manual_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('direction', 10)->default('cost');
            $table->foreignId('contractor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_name');
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('vat_rate', 5)->default('23');
            $table->string('default_unit', 20)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_templates');
        Schema::dropIfExists('document_items');
        Schema::dropIfExists('documents');
    }
};
