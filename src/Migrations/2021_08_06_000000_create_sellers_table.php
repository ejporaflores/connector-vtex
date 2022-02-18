<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSellersTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('account_sellers')) {
            echo 'Creating "account_sellers" table...' . PHP_EOL;
            Schema::create('account_sellers', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name', 60);
                $table->string('code', 60);
                $table->integer('account_id')->unsigned();
                $table->foreign('account_id')->references('id')
                    ->on('accounts')->onUpdate('cascade')->onDelete('cascade');
                $table->string('appkey', 100);
                $table->string('apptoken', 132);
                $table->string('base_uri')->nullable();
                $table->boolean('is_active')->default(true);
            });
        }
    }

    public function down()
    {
        Schema::disableForeignKeyConstraints();
        Schema::drop('account_sellers');
        Schema::enableForeignKeyConstraints();
    }
}
