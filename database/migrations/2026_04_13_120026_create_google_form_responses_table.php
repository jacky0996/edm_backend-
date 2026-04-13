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
        Schema::create('google_form_responses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id')->comment('活動 ID');
            $table->unsignedBigInteger('google_form_id')->comment('Google 表單 ID');
            $table->string('google_response_id')->comment('Google 回覆 ID')->unique();
            $table->json('answers')->comment('回覆內容 JSON 格式');
            $table->timestamp('submitted_at')->nullable()->comment('問卷送出時間');
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
        Schema::dropIfExists('google_form_responses');
    }
};
