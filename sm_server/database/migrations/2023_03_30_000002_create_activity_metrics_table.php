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
        Schema::create('activity_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('csv_upload_id')->constrained()->onDelete('cascade');
            $table->date('activity_date');
            $table->unsignedInteger('steps')->nullable();
            $table->decimal('distance', 8, 2)->nullable()->comment('Distance in kilometers');
            $table->unsignedInteger('active_minutes')->nullable();
            $table->timestamps();

            $table->index('activity_date');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('activity_metrics');
    }
};
