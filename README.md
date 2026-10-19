# Coordinates to Geometry (Spacialist plugin)

Calculates an ordinary WKT (geography) attribute from three other attributes of the same entity type, easting, northing and SRID, and keeps it in sync whenever one of them changes.

The point is transformed with PostGIS `ST_Transform` from the given SRID and stored like any other geography value (`geography_val`, EPSG:4326), so map, search and export work with it as usual.

## Usage

1. Add easting (double, integer or string), northing (double, integer or string), SRID (string or integer) and a WKT attribute to the entity type.
2. Open *Settings → Coordinates to Geometry*, pick the entity type and its four attributes. Saving marks the WKT attribute read-only for that entity type (`readonly: true` in the `entity_attributes` metadata) and recalculates all entities of the type, replacing existing WKT values.
3. Edit easting, northing or SRID of an entity. The WKT value updates on save.

SRID accepts `25832`, `EPSG:25832` or `urn:ogc:def:crs:EPSG::25832`. If a source value is missing, not a number, or the SRID is unknown to PostGIS, the WKT value is removed instead of showing a wrong position.

Pending moderation values are ignored. The WKT value changes when a moderator accepts the change.

If one of the four attributes is removed from the entity type, the mapping is removed and the WKT attribute becomes editable again. Its values are kept.

## Requirements in the core

The plugin is written against the planned `ListenerService` (see `<listeners>` in `plugin.xml`; the core schema needs a `<listeners>` element for it to validate). It expects these events:

| `on` | Payload properties | Dispatched from |
|---|---|---|
| `attribute_value.saved` | `attributeValue` (`App\AttributeValue`), `user` (`?App\User`) | `EntityAttributeObserver::saved` |
| `attribute_value.deleted` | `attributeValue`, `user` | `EntityAttributeObserver::deleted` (or `deleting`, the listener excludes the row) |
| `entity_type.attribute_removed` | `attributeId` (int), `entityTypeId` (int) | `EntityAttribute::removeFromEntityType()` |

The core also has to respect `readonly` in the `entity_attributes` metadata, in the attribute UI and in `AttributeValue::handlePatch`, `multieditAttributes` and the importer, otherwise the value can still be overwritten. The plugin's own writes go through Eloquent and must not be blocked.

The frontend reloads the WKT values whenever the selected entity's `updated_at` changes, because the core only applies the values the user edited after a save. This can go once the PATCH response returns the saved values.

## API

Mounted under `api/v1/coordinates` (the slug of the plugin name), all routes need authentication.

Changing the settings needs the plugin permission `coordinates_write` (from `permissions.json`), which has to be given to the roles that may use it.

| Route | Permission | |
|---|---|---|
| `GET /sources` | `coordinates_write` | List mappings |
| `POST /sources` | `coordinates_write` | Create or replace the mapping for `entity_type_id` + `wkt_attribute_id` and recalculate |
| `PUT /sources/{id}` | `coordinates_write` | Change a mapping and recalculate |
| `POST /sources/{id}/recompute` | `coordinates_write` | Recalculate all values of a mapping |
| `DELETE /sources/{id}` | `coordinates_write` | Remove a mapping, the WKT attribute becomes editable again (values stay) |
| `GET /entity/{id}/values` | `entity_read`, `entity_data_read` | WKT values of an entity, used to refresh the frontend |

## Tests

`tests/CoordinatesPluginTest.php` is a Spacialist feature test. Copy the plugin to `tests/assets/Plugins/Coordinates` (with a built `js/script.js`) and the test to `tests/Feature/` and run it with PHPUnit. Until the ListenerService exists, the test wires the listener to Eloquent events itself; replace that part once the core dispatches the events.

## Build

`make release` builds `build/spacialist-plugin-coordinates.zip` for upload.
