<?php namespace Quivi\Archive\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class BuilderTableCreateQuiviArchiveItems extends Migration
{
    public function up()
    {
        Schema::create('quivi_archive_items', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->integer('submission_id')->nullable();
            $table->integer('user_id')->nullable();
            $table->integer('validator_user_id')->nullable();
            $table->string('status', 15)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('quivi_archive_items');
    }
}
