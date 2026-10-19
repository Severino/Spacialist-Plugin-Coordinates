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
            $table->integer('entity_type_id')->unsigned();
            $table->integer('wkt_attribute_id')->unsigned();
            $table->integer('easting_attribute_id')->unsigned();
            $table->integer('northing_attribute_id')->unsigned();
            $table->integer('srid_attribute_id')->unsigned();
            $table->timestamps();

            $table->unique(['entity_type_id', 'wkt_attribute_id']);

            $table->foreign('entity_type_id')->references('id')->on('entity_types')->onDelete('cascade');
            $table->foreign('wkt_attribute_id')->references('id')->on('attributes')->onDelete('cascade');
            $table->foreign('easting_attribute_id')->references('id')->on('attributes')->onDelete('cascade');
            $table->foreign('northing_attribute_id')->references('id')->on('attributes')->onDelete('cascade');
            $table->foreign('srid_attribute_id')->references('id')->on('attributes')->onDelete('cascade');
        });
    }

    public function rollback() {
        Schema::dropIfExists('plugin_coordinates_sources');
    }
}
