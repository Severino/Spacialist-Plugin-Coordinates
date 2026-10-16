<?php

namespace App\Plugins\Coordinates\Migrations;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A named class instead of `return new class`: the core loads migrations with
// require_once, which returns no object when the file was already included.
class CreateCoordinatesSources {
    public function migrate() {
        Schema::create('plugin_coordinates_sources', function (Blueprint $table) {
            $table->id();
            $table->integer('geometry_attribute_id')->unsigned()->unique();
            $table->integer('easting_attribute_id')->unsigned();
            $table->integer('northing_attribute_id')->unsigned();
            $table->integer('srid_attribute_id')->unsigned();
            $table->timestamps();

            $table->foreign('geometry_attribute_id')->references('id')->on('attributes')->onDelete('cascade');
            $table->foreign('easting_attribute_id')->references('id')->on('attributes')->onDelete('cascade');
            $table->foreign('northing_attribute_id')->references('id')->on('attributes')->onDelete('cascade');
            $table->foreign('srid_attribute_id')->references('id')->on('attributes')->onDelete('cascade');
        });
    }

    public function rollback() {
        Schema::dropIfExists('plugin_coordinates_sources');
    }
}
