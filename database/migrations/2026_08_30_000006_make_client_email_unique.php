<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Email klien adalah identitas masuk portal (Portal\AuthController mencarinya
 * dengan where('email', ...)->first()), jadi harus unik. NULL boleh berulang —
 * klien tanpa email memang tidak memakai portal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', fn (Blueprint $table) => $table->unique('email'));
    }

    public function down(): void
    {
        Schema::table('clients', fn (Blueprint $table) => $table->dropUnique(['email']));
    }
};
