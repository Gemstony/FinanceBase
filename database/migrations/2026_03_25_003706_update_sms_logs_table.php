<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_logs', function (Blueprint $table) {
            // Add missing columns according to specification
            if (!Schema::hasColumn('sms_logs', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('subshop_id');
            }
            if (!Schema::hasColumn('sms_logs', 'template_id')) {
                $table->unsignedBigInteger('template_id')->nullable()->after('message');
            }
            if (!Schema::hasColumn('sms_logs', 'event')) {
                $table->string('event')->nullable()->after('template_id');
            }
            if (!Schema::hasColumn('sms_logs', 'provider_message_id')) {
                $table->string('provider_message_id')->nullable()->after('provider');
            }
            if (!Schema::hasColumn('sms_logs', 'attempts')) {
                $table->integer('attempts')->default(0)->after('provider_message_id');
            }
            if (!Schema::hasColumn('sms_logs', 'cost')) {
                $table->decimal('cost', 10, 4)->nullable()->after('attempts');
            }
            if (!Schema::hasColumn('sms_logs', 'error_code')) {
                $table->string('error_code')->nullable()->after('cost');
            }
            if (!Schema::hasColumn('sms_logs', 'error_message')) {
                $table->text('error_message')->nullable()->after('error_code');
            }
            if (!Schema::hasColumn('sms_logs', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable()->after('sent_at');
            }
            
            // Add indexes for performance
            if (!Schema::hasIndex('sms_logs', ['shop_id', 'event'])) {
                $table->index(['shop_id', 'event']);
            }
            if (!Schema::hasIndex('sms_logs', ['shop_id', 'status'])) {
                $table->index(['shop_id', 'status']);
            }
            if (!Schema::hasIndex('sms_logs', ['template_id'])) {
                $table->index(['template_id']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('sms_logs', function (Blueprint $table) {
            // Remove added columns
            $table->dropColumn(['user_id', 'template_id', 'event', 'provider_message_id', 'attempts', 'cost', 'error_code', 'error_message', 'delivered_at']);
            
            // Remove added indexes
            $table->dropIndex(['shop_id', 'event']);
            $table->dropIndex(['shop_id', 'status']);
            $table->dropIndex(['template_id']);
        });
    }
};