<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            // Catatan staf tetap internal kecuali ditandai dibagikan. Penandanya
            // eksplisit, bukan disimpulkan dari siapa penulisnya: kalau portal
            // menebak dari itu, satu catatan internal cukup untuk bocor.
            $table->boolean('shared_with_client')->default(false)->after('body');
            $table->index(['project_id', 'shared_with_client']);
        });

        Schema::table('attachments', function (Blueprint $table) {
            $table->foreignId('uploaded_by_client_id')->nullable()->after('uploaded_by')
                ->constrained('clients')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('uploaded_by_client_id');
        });

        Schema::table('notes', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'shared_with_client']);
            $table->dropColumn('shared_with_client');
            $table->dropConstrainedForeignId('client_id');
        });
    }
};
