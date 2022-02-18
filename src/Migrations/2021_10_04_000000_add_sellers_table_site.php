<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddSellersTableSite extends Migration
{
    public function up()
    {
        if (Schema::hasTable('account_sellers') && !Schema::hasColumn('account_sellers', 'site')) {
            echo 'Updating "account_sellers" table...' . PHP_EOL;
            Schema::table('account_sellers', function (Blueprint $table) {
                $table->string('site', 60)->nullable()->after('apptoken');
            });
        }
    }

    public function down()
    {
        // do nothing
    }
}
