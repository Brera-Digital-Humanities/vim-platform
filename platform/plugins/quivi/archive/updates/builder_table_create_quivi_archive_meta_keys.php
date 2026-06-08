<?php namespace Quivi\Archive\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class BuilderTableCreateQuiviArchiveMetaKeys extends Migration
{
    public function up()
    {
        Schema::create('quivi_archive_meta_keys', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->string('key', 255);
            $table->timestamp('deleted_at')->nullable();
            $table->unique('key');
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('quivi_archive_meta_keys');
    }
}
