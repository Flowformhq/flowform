<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->string('captcha_provider')->nullable()->after('webhook_secret');
            $table->text('captcha_secret')->nullable()->after('captcha_provider');
            $table->string('captcha_site_key')->nullable()->after('captcha_secret');
        });
    }

    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn(['captcha_provider', 'captcha_secret', 'captcha_site_key']);
        });
    }
};
