<?php

namespace App\Plugins\Coordinates\Services;

use App\AttributeTypes\GeographyAttribute;
use App\AttributeValue;
use App\Entity;
use App\EntityAttribute;
use App\Plugins\Coordinates\Models\Source;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Calculates the coordinate geometry value of an entity from its easting,
 * northing and SRID values and stores it as an EPSG:4326 geography.
 */
class GeometryCalculator
{
    /**
     * Recalculates every coordinate geometry attribute of the entity that uses
     * the given attribute as easting, northing or SRID.
     */
    public function recomputeForSourceAttribute(int $entityId, int $attributeId, ?int $userId = null, ?int $excludeValueId = null): void {
        foreach(Source::usingSource($attributeId) as $source) {
            $this->recompute($entityId, $source, $userId, $excludeValueId);
        }
    }

    /**
     * Recalculates the coordinate geometry value of all entities whose entity type has the WKT attribute.
     *
     * @return int number of entities that were processed
     */
    public function recomputeAll(Source $source, ?int $userId = null): int {
        $entityTypeIds = EntityAttribute::where('attribute_id', $source->geometry_attribute_id)
            ->pluck('entity_type_id');

        $count = 0;
        Entity::whereIn('entity_type_id', $entityTypeIds)
            ->select('id')
            ->chunkById(500, function($entities) use ($source, $userId, &$count) {
                foreach($entities as $entity) {
                    $this->recompute($entity->id, $source, $userId);
                    $count++;
                }
            });

        return $count;
    }

    /**
     * Recalculates one coordinate geometry value.
     *
     * When a source value is missing or invalid the computed value is removed,
     * so it never shows a position that does not match the source values.
     *
     * @param int|null $excludeValueId - a source value that is being deleted
     * @return AttributeValue|null the stored value, or null when it was removed
     */
    public function recompute(int $entityId, Source $source, ?int $userId = null, ?int $excludeValueId = null): ?AttributeValue {
        $entity = Entity::find($entityId);
        if(!isset($entity) || !$this->entityHasAttribute($entity, $source->geometry_attribute_id)) {
            return null;
        }

        // The ModerationScope on AttributeValue excludes pending values,
        // so only accepted values are used for the calculation.
        $values = AttributeValue::where('entity_id', $entityId)
            ->whereIn('attribute_id', $source->sourceAttributeIds())
            ->when($excludeValueId !== null, fn($query) => $query->where('id', '<>', $excludeValueId))
            ->get()
            ->keyBy('attribute_id');

        $easting = self::toNumber($values->get($source->easting_attribute_id));
        $northing = self::toNumber($values->get($source->northing_attribute_id));
        $srid = self::parseSrid($values->get($source->srid_attribute_id)?->str_val);

        $wkt = null;
        if($easting !== null && $northing !== null && $srid !== null) {
            $wkt = $this->transform($easting, $northing, $srid);
        }

        $current = AttributeValue::where('entity_id', $entityId)
            ->where('attribute_id', $source->geometry_attribute_id)
            ->first();

        if($wkt === null) {
            $current?->delete();
            return null;
        }

        $value = $current ?? new AttributeValue([
            'entity_id' => $entityId,
            'attribute_id' => $source->geometry_attribute_id,
        ]);
        $geometry = GeographyAttribute::unserialize($wkt);
        if(!$value->exists || GeographyAttribute::serialize($value->geography_val) !== GeographyAttribute::serialize($geometry)) {
            $value->geography_val = $geometry;
            $value->user_id = $userId ?? $value->user_id ?? $values->first()?->user_id;
            $value->save();
        }

        return $value;
    }

    /**
     * Transforms the point to EPSG:4326 and returns it as EWKT,
     * or null when PostGIS does not know the SRID.
     */
    public function transform(float $easting, float $northing, int $srid): ?string {
        $known = DB::table('spatial_ref_sys')->where('srid', $srid)->exists();
        if(!$known) {
            Log::info("Coordinates: unknown SRID {$srid}");
            return null;
        }

        $row = DB::selectOne(
            'SELECT ST_AsEWKT(ST_Transform(ST_SetSRID(ST_MakePoint(?, ?), ?), 4326)) AS wkt',
            [$easting, $northing, $srid]
        );

        return $row?->wkt;
    }

    /**
     * Accepts "25832", "EPSG:25832", "epsg 25832" or "urn:ogc:def:crs:EPSG::25832".
     */
    public static function parseSrid(?string $value): ?int {
        if($value === null) {
            return null;
        }
        if(preg_match('/^\s*(?:(?:urn:ogc:def:crs:)?epsg\s*:{0,2}\s*)?(\d{4,6})\s*$/i', $value, $matches) !== 1) {
            return null;
        }
        return intval($matches[1]);
    }

    /**
     * Reads a number from a double, integer or numeric string value.
     * A comma is accepted as decimal separator.
     */
    public static function toNumber(?AttributeValue $value): ?float {
        if($value === null) {
            return null;
        }
        if($value->dbl_val !== null) {
            return floatval($value->dbl_val);
        }
        if($value->int_val !== null) {
            return floatval($value->int_val);
        }
        if($value->str_val !== null) {
            $normalized = str_replace(',', '.', trim($value->str_val));
            return is_numeric($normalized) ? floatval($normalized) : null;
        }
        return null;
    }

    private function entityHasAttribute(Entity $entity, int $attributeId): bool {
        return EntityAttribute::where('entity_type_id', $entity->entity_type_id)
            ->where('attribute_id', $attributeId)
            ->exists();
    }
}
