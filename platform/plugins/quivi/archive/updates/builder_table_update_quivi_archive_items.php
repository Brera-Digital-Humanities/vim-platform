<?php namespace Quivi\Archive\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class BuilderTableUpdateQuiviArchiveItems extends Migration
{
    public function up()
    {
        Schema::table('quivi_archive_items', function($table)
        {
            $table->text('data')->nullable()->after('validator_user_id');
        });
    }
    
    public function down()
    {
        Schema::table('quivi_archive_items', function($table)
        {
            $table->dropColumn('data');
        });
    }
}
