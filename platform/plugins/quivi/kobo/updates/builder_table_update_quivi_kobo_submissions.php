<?php namespace Quivi\Kobo\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class BuilderTableUpdateQuiviKoboSubmissions extends Migration
{
    public function up()
    {
        Schema::table('quivi_kobo_submissions', function($table)
        {
            $table->string('validation', 15)->nullable()->after('status');
        });
    }
    
    public function down()
    {
        Schema::table('quivi_kobo_submissions', function($table)
        {
            $table->dropColumn('validation');
        });
    }
}
