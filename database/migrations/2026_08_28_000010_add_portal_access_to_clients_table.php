<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('password')->nullable()->after('status');
            $table->boolean('portal_enabled')->default(false)->after('password');
            $table->timestamp('last_login_at')->nullable()->after('portal_enabled');
            $table->rememberToken();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['password', 'portal_enabled', 'last_login_at', 'remember_token']);
        });
    }
};
