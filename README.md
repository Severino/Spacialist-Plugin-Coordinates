# Coordinates to Geometry (Spacialist plugin)

Adds the attribute type **Coordinate geometry** (`coordinates-geometry`). Its value is calculated from three ordinary attributes, easting, northing and SRID, and kept in sync whenever one of them changes. Users never edit it directly.

The value is stored like a core geography value (`geography_val`, EPSG:4326), so map, search and export can treat it like any other geography attribute. The point is transformed with PostGIS `ST_Transform` from the given SRID.

## Usage

1. Add the attributes easting (double), northing (double), SRID (string) and a Coordinate geometry attribute, and add all four to the entity type.
2. Open *Settings → Coordinates to Geometry* and choose which attributes belong together. Saving calculates all existing values.
3. Edit easting, northing or SRID of an entity. The computed value updates on save.

SRID accepts `25832`, `EPSG:25832` or `urn:ogc:def:crs:EPSG::25832`. If a source value is missing, not a number, or the SRID is unknown to PostGIS, the computed value is removed instead of showing a wrong position.

Pending moderation values are ignored. The computed value changes when a moderator accepts the change.

## Requirements in the core

The plugin is written against the planned `ListenerService` (see `<listeners>` in `plugin.xml`; the core schema needs a `<listeners>` element for it to validate). It expects these events:

| `on` | Payload properties | Dispatched from |
|---|---|---|
| `attribute_value.saved` | `attributeValue` (`App\AttributeValue`), `user` (`?App\User`) | `EntityAttributeObserver::saved` |
| `attribute_value.deleted` | `attributeValue`, `user` | `EntityAttributeObserver::deleted` (or `deleting`, the listener excludes the row) |
| `entity_type.attribute_removed` | `attributeId` (int), `entityTypeId` (int) | `EntityAttribute::removeFromEntityType()` |

It also sets `protected static bool $readonly = true` and `isReadonly()` on the attribute type. The core needs to check that in `AttributeValue::handlePatch`, `multieditAttributes` and the importer, otherwise the value can still be overwritten through the API.

## API

Mounted under `api/v1/coordinates` (the slug of the plugin name), all routes need authentication.

| Route | Permission | |
|---|---|---|
| `GET /sources` | `attribute_read` | List mappings |
| `POST /sources` | `attribute_write` | Create or replace the mapping for `geometry_attribute_id` and recalculate |
| `POST /sources/{id}/recompute` | `attribute_write` | Recalculate all values of a mapping |
| `DELETE /sources/{id}` | `attribute_write` | Remove a mapping (values stay) |

## Tests

`tests/CoordinatesPluginTest.php` is a Spacialist feature test. Copy the plugin to `tests/assets/Plugins/Coordinates` (with a built `js/script.js`) and the test to `tests/Feature/` and run it with PHPUnit. Until the ListenerService exists, the test wires the listener to Eloquent events itself; replace that part once the core dispatches the events.

## Build

`make release` builds `build/spacialist-plugin-coordinates.zip` for upload.
