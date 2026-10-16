<?php

namespace App\Plugins\Coordinates\Models;

use App\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * Maps one coordinate geometry attribute to the three attributes it is calculated from.
 */
class Source extends Model
{
    protected $table = 'coordinates_sources';

    protected $fillable = [
        'geometry_attribute_id',
        'easting_attribute_id',
        'northing_attribute_id',
        'srid_attribute_id',
    ];

    protected $casts = [
        'geometry_attribute_id' => 'integer',
        'easting_attribute_id' => 'integer',
        'northing_attribute_id' => 'integer',
        'srid_attribute_id' => 'integer',
    ];

    public function sourceAttributeIds(): array {
        return [
            $this->easting_attribute_id,
            $this->northing_attribute_id,
            $this->srid_attribute_id,
        ];
    }

    public function geometryAttribute() {
        return $this->belongsTo(Attribute::class, 'geometry_attribute_id');
    }

    /**
     * All mappings that use the given attribute as easting, northing or SRID.
     */
    public static function usingSource(int $attributeId) {
        return static::where('easting_attribute_id', $attributeId)
            ->orWhere('northing_attribute_id', $attributeId)
            ->orWhere('srid_attribute_id', $attributeId)
            ->get();
    }
}
