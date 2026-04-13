<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('google_form_responses', function (Blueprint $table) {
            $table->tinyInteger('status')->default(0)->comment('0:待審, 1:通過, 2:退件')->after('submitted_at');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('google_form_responses', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
