<?php namespace Quivi\Archive\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class BuilderTableUpdateQuiviArchiveItems2 extends Migration
{
    public function up()
    {
        Schema::table('quivi_archive_items', function($table)
        {
            $table->string('submission_source', 255)->nullable()->after('submission_id');
            $table->unique(['submission_source', 'submission_id']);
        });
    }
    
    public function down()
    {
        Schema::table('quivi_archive_items', function($table)
        {
            $table->dropUnique(['submission_source', 'submission_id']);
            $table->dropColumn('submission_source');
        });
    }
}
