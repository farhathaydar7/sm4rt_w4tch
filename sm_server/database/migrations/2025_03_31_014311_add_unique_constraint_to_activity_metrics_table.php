<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('activity_metrics', function (Blueprint $table) {
            // Add a unique constraint on user_id and activity_date
            $table->unique(['user_id', 'activity_date'], 'activity_metrics_user_date_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('activity_metrics', function (Blueprint $table) {
            // Drop the unique constraint
            $table->dropUnique('activity_metrics_user_date_unique');
        });
    }
};
