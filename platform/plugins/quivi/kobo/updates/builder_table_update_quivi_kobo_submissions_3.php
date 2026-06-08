<?php namespace Quivi\Kobo\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class BuilderTableUpdateQuiviKoboSubmissions3 extends Migration
{
    public function up()
    {
        Schema::table('quivi_kobo_submissions', function($table)
        {
            $table->dateTime('reviewed_at')->nullable();
        });
    }
    
    public function down()
    {
        Schema::table('quivi_kobo_submissions', function($table)
        {
            $table->dropColumn('reviewed_at');
        });
    }
}
