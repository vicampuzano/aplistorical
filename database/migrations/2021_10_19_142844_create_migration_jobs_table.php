<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMigrationJobsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('migration_jobs', function (Blueprint $table) {
            $table->id();
            $table->text('job_label')->default("Untitled Migration");
            $table->json('source_config');
            $table->json('destination_config');
            $table->dateTime('last_downloaded');
            $table->dateTime('last_translated');
            $table->dateTime('last_uploaded');
            $table->boolean('preserve_sources')->default(false);
            $table->boolean('preserve_translations')->default(false);
            $table->integer('source_ganularity')->unsigned()->default(0);
            $table->integer('destination_batch')->unsigned()->default(10000);
            $table->integer('sleep_interval')->unsigned()->default(500);
            $table->boolean('parallelize_translations');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('migration_jobs');
    }
}
