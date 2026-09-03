<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_rates', function (Blueprint $table) {
            $table->id();
            $table->string('service_type');
            $table->string('unit');
            $table->string('label');
            $table->decimal('unit_price', 14, 2);
            // Biaya minimum: pekerjaan kecil tetap menuntut mobilisasi kru dan
            // waktu olah yang tidak menyusut sebanding luasnya.
            $table->decimal('min_charge', 14, 2)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            // Satu tarif per satuan per layanan; dua baris yang bersaing membuat
            // usulan harganya bergantung urutan baris, bukan aturan.
            $table->unique(['service_type', 'unit']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_rates');
    }
};
