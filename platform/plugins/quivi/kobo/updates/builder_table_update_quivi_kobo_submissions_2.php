<?php namespace Quivi\Kobo\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class BuilderTableUpdateQuiviKoboSubmissions2 extends Migration
{
    public function up()
    {
        Schema::table('quivi_kobo_submissions', function($table)
        {
            $table->integer('validator_user_id')->nullable()->unsigned()->after('user_id');
            $table->dateTime('validated_at')->nullable();
        });
    }
    
    public function down()
    {
        Schema::table('quivi_kobo_submissions', function($table)
        {
            $table->dropColumn('validator_user_id');
            $table->dropColumn('validated_at');
        });
    }
}
