<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('digest_daily')->default(false)->after('avatar_path');
            $table->boolean('digest_weekly')->default(false)->after('digest_daily');
            // So a digest goes out only once, even if the scheduler runs twice.
            $table->date('digest_daily_sent_on')->nullable()->after('digest_weekly');
            $table->date('digest_weekly_sent_on')->nullable()->after('digest_daily_sent_on');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['digest_daily', 'digest_weekly', 'digest_daily_sent_on', 'digest_weekly_sent_on']);
        });
    }
};
