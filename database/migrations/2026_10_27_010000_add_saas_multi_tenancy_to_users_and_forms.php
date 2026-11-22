<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('password');
            $table->string('plan')->default('free')->after('is_admin');
            $table->unsignedInteger('monthly_quota')->default(50)->after('plan');
            $table->string('payment_reference_id')->nullable()->after('monthly_quota');
            $table->string('subscription_status')->default('active')->after('payment_reference_id');
            $table->timestamp('subscription_expires_at')->nullable()->after('subscription_status');
        });

        Schema::table('forms', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            $table->string('access_key')->nullable()->unique()->after('slug');
            $table->text('allowed_domains')->nullable()->after('redirect_url');
        });

        // Seed existing first user as admin with Pro tier
        $adminUser = DB::table('users')->orderBy('id')->first();
        if ($adminUser) {
            DB::table('users')->where('id', $adminUser->id)->update([
                'is_admin' => true,
                'plan' => 'pro',
                'monthly_quota' => 100000,
            ]);

            // Assign existing forms to admin and backfill access keys
            $forms = DB::table('forms')->get();
            foreach ($forms as $form) {
                DB::table('forms')->where('id', $form->id)->update([
                    'user_id' => $adminUser->id,
                    'access_key' => 'ff_' . strtolower(Str::random(10)),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn(['user_id', 'access_key', 'allowed_domains']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_admin', 'plan', 'monthly_quota', 'payment_reference_id', 'subscription_status', 'subscription_expires_at']);
        });
    }
};
