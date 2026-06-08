<?php namespace Quivi\Archive\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class BuilderTableCreateQuiviArchiveMetaValues extends Migration
{
    public function up()
    {
        Schema::create('quivi_archive_meta_values', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->integer('meta_key_id')->unsigned();
            $table->string('lang', 2);
            $table->string('value', 255)->nullable();
            $table->unique(['meta_key_id','lang']);
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('quivi_archive_meta_values');
    }
}
