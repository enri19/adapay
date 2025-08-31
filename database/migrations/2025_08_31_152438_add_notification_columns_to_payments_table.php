<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->timestamp('notified_invoice_at')->nullable()->after('paid_at');
            $table->timestamp('notified_paid_at')->nullable()->after('notified_invoice_at');
            $table->index('notified_invoice_at');
            $table->index('notified_paid_at');
        });
    }

    public function down()
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['notified_invoice_at']);
            $table->dropIndex(['notified_paid_at']);
            $table->dropColumn(['notified_invoice_at', 'notified_paid_at']);
        });
    }
};
