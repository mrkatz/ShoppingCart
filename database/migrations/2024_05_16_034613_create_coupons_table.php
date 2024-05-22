<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCouponsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create(config('cart.database.table.coupons'), function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('type');
            $table->double('value', 12, 2);
            $table->double('minimum_spend', 12, 2)->nullable();
            $table->double('maximum_spend', 12, 2)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->integer("use_limit")->nullable();
            $table->string("use_device")->nullable();
            $table->boolean("multiple_use")->default(0);
            $table->integer("total_use")->default(0);
            $table->boolean("status")->default(0);
            $table->json('options');
            $table->timestamps();
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::drop(config('cart.database.table.coupons'));
    }
}
