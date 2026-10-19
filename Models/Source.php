<?php

namespace App\Plugins\Coordinates\Models;

use App\EntityAttribute;
use Illuminate\Database\Eloquent\Model;

/**
 * Maps the WKT attribute of an entity type to the easting, northing and SRID
 * attributes of the same entity type it is calculated from.
 */
class Source extends Model
{
    protected $table = 'plugin_coordinates_sources';

    protected $fillable = [
        'entity_type_id',
        'wkt_attribute_id',
        'easting_attribute_id',
        'northing_attribute_id',
        'srid_attribute_id',
    ];

    protected $casts = [
        'entity_type_id' => 'integer',
        'wkt_attribute_id' => 'integer',
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

    public function attributeIds(): array {
        return [$this->wkt_attribute_id, ...$this->sourceAttributeIds()];
    }

    /**
     * All mappings of the entity type that use the given attribute as easting, northing or SRID.
     */
    public static function usingSource(int $entityTypeId, int $attributeId) {
        return static::where('entity_type_id', $entityTypeId)
            ->where(function($query) use ($attributeId) {
                $query->where('easting_attribute_id', $attributeId)
                    ->orWhere('northing_attribute_id', $attributeId)
                    ->orWhere('srid_attribute_id', $attributeId);
            })
            ->get();
    }

    /**
     * Sets or removes the readonly flag on the WKT attribute of the entity type,
     * so users can't edit a value that is calculated.
     */
    public function setTargetReadonly(bool $readonly): void {
        $entityAttribute = EntityAttribute::where('entity_type_id', $this->entity_type_id)
            ->where('attribute_id', $this->wkt_attribute_id)
            ->first();
        if(!isset($entityAttribute)) {
            return;
        }

        $metadata = $entityAttribute->metadata ?? new \stdClass();
        if($readonly) {
            $metadata->readonly = true;
        } else {
            unset($metadata->readonly);
        }
        $entityAttribute->metadata = $metadata;
        $entityAttribute->save();
    }
}
