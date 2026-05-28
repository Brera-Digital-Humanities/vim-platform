<?php namespace Quivi\Kobo\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class BuilderTableCreateQuiviKoboSubmissions extends Migration
{
    public function up()
    {
        Schema::create('quivi_kobo_submissions', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->integer('user_id')->unsigned();
            $table->string('asset_uid', 64)->nullable();
            $table->bigInteger('kobo_id')->nullable()->unsigned();
            $table->string('kobo_uuid', 36)->nullable();
            $table->string('status', 15)->default('created');
            $table->text('error')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['kobo_id']);
            $table->unique(['kobo_uuid']);
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('quivi_kobo_submissions');
    }
}
