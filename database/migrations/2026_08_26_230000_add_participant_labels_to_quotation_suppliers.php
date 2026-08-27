<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotation_suppliers', function (Blueprint $table) {
            if (! Schema::hasColumn('quotation_suppliers', 'display_name')) {
                $table->string('display_name')->nullable()->after('supplier_id');
            }
        });

        Schema::table('quotation_prices', function (Blueprint $table) {
            if (! Schema::hasColumn('quotation_prices', 'quotation_supplier_id')) {
                $table->foreignId('quotation_supplier_id')->nullable()->after('purchase_list_item_id')->constrained('quotation_suppliers')->cascadeOnDelete();
            }
        });

        DB::table('quotation_prices')
            ->whereNull('quotation_supplier_id')
            ->orderBy('id')
            ->chunkById(200, function ($prices) {
                foreach ($prices as $price) {
                    $participant = DB::table('quotation_suppliers')
                        ->where('quotation_id', $price->quotation_id)
                        ->where('supplier_id', $price->supplier_id)
                        ->first();

                    if ($participant) {
                        DB::table('quotation_prices')
                            ->where('id', $price->id)
                            ->update(['quotation_supplier_id' => $participant->id]);
                    }
                }
            });

        $this->dropUniqueIfExists('quotation_suppliers', 'quotation_suppliers_quotation_id_supplier_id_unique');
        $this->dropUniqueIfExists('quotation_prices', 'quotation_price_unique');

        try {
            Schema::table('quotation_prices', function (Blueprint $table) {
                $table->unique(['quotation_id', 'purchase_list_item_id', 'quotation_supplier_id'], 'quotation_participant_price_unique');
            });
        } catch (Throwable) {
            // Pode ja existir em bases que receberam o ajuste por SQL manual.
        }
    }

    public function down(): void
    {
        Schema::table('quotation_suppliers', function (Blueprint $table) {
            if (Schema::hasColumn('quotation_suppliers', 'display_name')) {
                $table->dropColumn('display_name');
            }
        });

        Schema::table('quotation_prices', function (Blueprint $table) {
            if (Schema::hasColumn('quotation_prices', 'quotation_supplier_id')) {
                $table->dropConstrainedForeignId('quotation_supplier_id');
            }
        });
    }

    private function dropUniqueIfExists(string $table, string $index): void
    {
        try {
            Schema::table($table, fn (Blueprint $table) => $table->dropUnique($index));
        } catch (Throwable) {
            // Banco local e hospedagem podem ter nomes/estado de indice diferentes.
        }
    }
};
