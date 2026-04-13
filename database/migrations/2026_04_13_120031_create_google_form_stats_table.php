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
        Schema::create('google_form_stats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id')->comment('活動 ID');
            $table->unsignedBigInteger('google_form_id')->nullable()->comment('Google 表單 ID');
            $table->unsignedInteger('view_count')->default(0)->comment('讀取/檢視問卷人數');
            $table->unsignedInteger('response_count')->default(0)->comment('實際回答人數');
            $table->timestamps();

            $table->index('event_id');
            $table->index('google_form_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('google_form_stats');
    }
};
