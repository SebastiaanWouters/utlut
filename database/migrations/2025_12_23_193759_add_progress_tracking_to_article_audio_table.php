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
        Schema::table('article_audio', function (Blueprint $table) {
            $table->unsignedTinyInteger('progress_percent')->default(0)->after('status');
            $table->unsignedInteger('estimated_duration_ms')->nullable()->after('progress_percent');
            $table->timestamp('processing_started_at')->nullable()->after('estimated_duration_ms');
            $table->timestamp('processing_completed_at')->nullable()->after('processing_started_at');
            $table->unsignedSmallInteger('total_chunks')->default(1)->after('processing_completed_at');
            $table->unsignedSmallInteger('completed_chunks')->default(0)->after('total_chunks');
            $table->unsignedTinyInteger('retry_count')->default(0)->after('completed_chunks');
            $table->timestamp('next_retry_at')->nullable()->after('retry_count');
            $table->string('error_code', 50)->nullable()->after('error_message');
            $table->unsignedInteger('content_length')->nullable()->after('error_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('article_audio', function (Blueprint $table) {
            $table->dropColumn([
                'progress_percent',
                'estimated_duration_ms',
                'processing_started_at',
                'processing_completed_at',
                'total_chunks',
                'completed_chunks',
                'retry_count',
                'next_retry_at',
                'error_code',
                'content_length',
            ]);
        });
    }
};
