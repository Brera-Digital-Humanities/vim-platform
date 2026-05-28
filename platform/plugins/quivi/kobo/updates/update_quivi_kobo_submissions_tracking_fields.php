<?php namespace Quivi\Kobo\Updates;

use DB;
use Schema;
use Winter\Storm\Database\Updates\Migration;

class UpdateQuiviKoboSubmissionsTrackingFields extends Migration
{
    public function up()
    {
        Schema::table('quivi_kobo_submissions', function ($table) {
            if (!Schema::hasColumn('quivi_kobo_submissions', 'asset_uid')) {
                $table->string('asset_uid', 64)->nullable()->after('user_id');
            }

            if (!Schema::hasColumn('quivi_kobo_submissions', 'error')) {
                $table->text('error')->nullable()->after('status');
            }
        });

        if (Schema::hasColumn('quivi_kobo_submissions', 'log')) {
            if (Schema::hasColumn('quivi_kobo_submissions', 'error')) {
                DB::statement('UPDATE quivi_kobo_submissions SET error = log WHERE error IS NULL AND log IS NOT NULL');
            }

            Schema::table('quivi_kobo_submissions', function ($table) {
                $table->dropColumn('log');
            });
        }

        DB::statement("ALTER TABLE quivi_kobo_submissions MODIFY kobo_id BIGINT UNSIGNED NULL");
        DB::statement("ALTER TABLE quivi_kobo_submissions MODIFY status VARCHAR(15) NOT NULL DEFAULT 'created'");
    }

    public function down()
    {
        Schema::table('quivi_kobo_submissions', function ($table) {
            if (!Schema::hasColumn('quivi_kobo_submissions', 'log')) {
                $table->text('log')->nullable()->after('status');
            }
        });

        if (Schema::hasColumn('quivi_kobo_submissions', 'error') && Schema::hasColumn('quivi_kobo_submissions', 'log')) {
            DB::statement('UPDATE quivi_kobo_submissions SET log = error WHERE log IS NULL AND error IS NOT NULL');
        }

        Schema::table('quivi_kobo_submissions', function ($table) {
            if (Schema::hasColumn('quivi_kobo_submissions', 'asset_uid')) {
                $table->dropColumn('asset_uid');
            }

            if (Schema::hasColumn('quivi_kobo_submissions', 'error')) {
                $table->dropColumn('error');
            }
        });

        DB::statement("ALTER TABLE quivi_kobo_submissions MODIFY kobo_id INT UNSIGNED NULL");
        DB::statement("ALTER TABLE quivi_kobo_submissions MODIFY status VARCHAR(15) NULL DEFAULT NULL");
    }
}
