<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // We need to modify the enum values
        // In MySQL, we need to alter the column to add the new values
        DB::statement("ALTER TABLE csv_uploads MODIFY status ENUM('pending', 'processing', 'processed', 'partially_processed', 'failed') NOT NULL DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Revert back to the original values
        DB::statement("ALTER TABLE csv_uploads MODIFY status ENUM('pending', 'processed', 'failed') NOT NULL DEFAULT 'pending'");
    }
};
