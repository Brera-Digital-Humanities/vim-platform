<?php namespace Quivi\Kobo\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class BuilderTableUpdateQuiviKoboSubmissions4 extends Migration
{
    public function up()
    {
        Schema::table('quivi_kobo_submissions', function($table)
        {
            $table->text('notes')->nullable()->after('error');
        });
    }
    
    public function down()
    {
        Schema::table('quivi_kobo_submissions', function($table)
        {
            $table->dropColumn('notes');
        });
    }
}
