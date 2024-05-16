<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCartFeesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create(config('cart.database.table.fees'), function (Blueprint $table) {
            $table->id();
            // $table->string('code');
            // $table->string('type');
            // $table->double('amount', 12, 2);
            // $table->double('minimum_spend', 12, 2)->nullable();
            // $table->double('maximum_spend', 12, 2)->nullable();
            // $table->date('start_date');
            // $table->date('end_date');
            // $table->integer("use_limit")->nullable();
            // $table->string("use_device")->nullable();
            // $table->enum("multiple_use", ["yes", "no"])->default("no");
            // $table->integer("total_use")->default(0);
            // $table->integer("status")->default(0);
            // $table->timestamps();
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::drop(config('cart.database.table.fees'));
    }
}
