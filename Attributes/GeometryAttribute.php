<?php

namespace App\Plugins\Coordinates\Attributes;

use App\AttributeTypes\GeographyAttribute;
use App\Exceptions\InvalidDataException;

/**
 * Geography attribute whose value is calculated by the plugin from an
 * easting, a northing and an SRID attribute (see Models\Source).
 *
 * The value is stored like a core geography value (geography_val, EPSG:4326),
 * so map, search and export treat it like any other geography attribute.
 */
class GeometryAttribute extends GeographyAttribute
{
    protected static string $type = 'coordinates-geometry';
    protected static bool $inTable = false;
    protected static ?string $field = 'geography_val';
    protected static ?string $label = 'plugin.coordinates.attribute_name';

    /**
     * Read by the core to refuse writes through the entity data endpoints.
     */
    protected static bool $readonly = true;

    public static function isReadonly(): bool {
        return static::$readonly;
    }

    public static function parseImport(int|float|bool|string $data): mixed {
        throw new InvalidDataException(__('Coordinate geometries are calculated and cannot be imported.'));
    }
}
