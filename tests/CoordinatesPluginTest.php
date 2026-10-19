<?php

namespace Tests\Feature;

use App\Attribute;
use App\AttributeValue;
use App\Entity;
use App\EntityAttribute;
use App\Plugin;
use App\Plugins\Coordinates\Listeners\GeometrySync;
use App\Plugins\Coordinates\Models\Source;
use App\Services\PluginManager;
use App\ThConcept;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\PluginTestCase;

/**
 * Local integration test for the Coordinates plugin.
 *
 * The core ListenerService does not exist yet, so setUp() wires the plugin
 * listener to Eloquent events the way the planned core events would.
 */
class CoordinatesPluginTest extends PluginTestCase {
    private Entity $entity;
    private array $aid = [];

    protected function setUp(): void {
        parent::setUp();

        $plugin = Plugin::create([
            'name' => 'Coordinates',
            'version' => '1.0.0',
            'uuid' => (string) Str::uuid(),
        ]);
        app(PluginManager::class)->install($plugin);
        app(PluginManager::class)->routeService->mapRoutes();
        app('router')->getRoutes()->refreshNameLookups();

        // Stand-in for the core events attribute_value.saved / attribute_value.deleted
        Event::listen('eloquent.saved: ' . AttributeValue::class, function(AttributeValue $value) {
            app(GeometrySync::class)->onSaved((object) ['attributeValue' => $value, 'user' => auth()->user()]);
        });
        Event::listen('eloquent.deleted: ' . AttributeValue::class, function(AttributeValue $value) {
            app(GeometrySync::class)->onDeleted((object) ['attributeValue' => $value, 'user' => auth()->user()]);
        });

        $role = $this->user->roles()->first();
        $role->givePermissionTo('coordinates_write');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->user->unsetRelation('roles');

        $this->entity = Entity::find(1);
        $url = ThConcept::first()->concept_url;
        foreach(['easting' => 'double', 'northing' => 'double', 'srid' => 'string', 'wkt' => 'geography'] as $key => $datatype) {
            $attribute = new Attribute();
            $attribute->thesaurus_url = $url;
            $attribute->datatype = $datatype;
            $attribute->recursive = false;
            $attribute->save();
            EntityAttribute::create([
                'entity_type_id' => $this->entity->entity_type_id,
                'attribute_id' => $attribute->id,
                'position' => 100 + count($this->aid),
            ]);
            $this->aid[$key] = $attribute->id;
        }
    }

    private function configure(array $overrides = []) {
        return $this->userRequest()->post('/api/v1/coordinates/sources', [
            'entity_type_id' => $this->entity->entity_type_id,
            'wkt_attribute_id' => $this->aid['wkt'],
            'easting_attribute_id' => $this->aid['easting'],
            'northing_attribute_id' => $this->aid['northing'],
            'srid_attribute_id' => $this->aid['srid'],
            ...$overrides,
        ]);
    }

    private function wktReadonly(): bool {
        $metadata = EntityAttribute::where('entity_type_id', $this->entity->entity_type_id)
            ->where('attribute_id', $this->aid['wkt'])
            ->first()
            ->metadata;
        return ($metadata->readonly ?? false) === true;
    }

    private function patchValues(array $values, string $op = 'replace') {
        $patches = [];
        foreach($values as $key => $value) {
            $patches[] = [
                'op' => $op,
                'params' => ['aid' => $this->aid[$key]],
                'value' => $value,
            ];
        }
        return $this->userRequest()->patch("/api/v1/entity/{$this->entity->id}/attributes", $patches);
    }

    private function wkt(): ?AttributeValue {
        return AttributeValue::where('entity_id', $this->entity->id)
            ->where('attribute_id', $this->aid['wkt'])
            ->first();
    }

    private function lonLat(): array {
        $row = DB::selectOne(
            'SELECT ST_X(geography_val::geometry) AS lon, ST_Y(geography_val::geometry) AS lat FROM attribute_values WHERE id = ?',
            [$this->wkt()->id]
        );
        return [$row->lon, $row->lat];
    }

    public function testConfigureRejectsWrongDatatypes(): void {
        $this->assertStatus($this->configure(['wkt_attribute_id' => $this->aid['northing'], 'northing_attribute_id' => $this->aid['wkt']]), 422);
        $this->assertStatus($this->configure(['srid_attribute_id' => $this->aid['easting'], 'easting_attribute_id' => $this->aid['srid']]), 422);
        $this->assertEquals(0, Source::count());
    }

    public function testConfigureRejectsAttributesOfOtherEntityTypes(): void {
        $otherType = \App\EntityType::where('id', '<>', $this->entity->entity_type_id)->first();
        $this->assertStatus($this->configure(['entity_type_id' => $otherType->id]), 422);
    }

    public function testConfigureRequiresPermission(): void {
        $role = $this->user->roles()->first();
        $role->revokePermissionTo('coordinates_write');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->user->unsetRelation('roles');

        $this->assertStatus($this->configure(), 403);
        $this->userRequest()->get('/api/v1/coordinates/sources')->assertStatus(403);
    }

    public function testConfigureMarksWktReadonlyAndDeleteUnmarks(): void {
        $this->assertFalse($this->wktReadonly());
        $this->assertStatus($this->configure(), 201);
        $this->assertTrue($this->wktReadonly());

        $source = Source::first();
        $this->userRequest()->delete("/api/v1/coordinates/sources/{$source->id}")->assertStatus(204);
        $this->assertFalse($this->wktReadonly());
    }

    public function testConfigureReplacesExistingWktValues(): void {
        $this->patchValues(['wkt' => 'POLYGON((0 0, 1 0, 1 1, 0 0))']);
        $this->assertNotNull($this->wkt());

        $this->configure();
        $this->assertNull($this->wkt());

        $this->patchValues(['easting' => 500000, 'northing' => 5400000, 'srid' => '25832']);
        $this->assertStringStartsWith('SRID=4326;POINT', $this->wkt()->getValue());
    }

    public function testValuesEndpointReturnsWktValues(): void {
        $this->configure();
        $response = $this->userRequest()->get("/api/v1/coordinates/entity/{$this->entity->id}/values");
        $response->assertStatus(200)->assertJson([$this->aid['wkt'] => null]);

        $this->patchValues(['easting' => 500000, 'northing' => 5400000, 'srid' => '25832']);
        $response = $this->userRequest()->get("/api/v1/coordinates/entity/{$this->entity->id}/values");
        $response->assertStatus(200);
        $this->assertStringStartsWith('SRID=4326;POINT', $response->json((string) $this->aid['wkt'])['value']);
    }

    public function testPatchCalculatesWkt(): void {
        $this->assertStatus($this->configure(), 201);
        $this->assertNull($this->wkt());

        $this->assertStatus($this->patchValues([
            'easting' => 500000,
            'northing' => 5400000,
            'srid' => 'EPSG:25832',
        ]), 200);

        [$lon, $lat] = $this->lonLat();
        $this->assertEqualsWithDelta(9.0, $lon, 0.000001);
        $this->assertEqualsWithDelta(48.7530, $lat, 0.001);
        $this->assertStringStartsWith('SRID=4326;POINT', $this->wkt()->getValue());
    }

    public function testMultieditUpdatesWkt(): void {
        $this->configure();
        $this->patchValues(['easting' => 500000, 'northing' => 5400000, 'srid' => '25832']);
        [, $latBefore] = $this->lonLat();

        $response = $this->userRequest()->patch('/api/v1/entity/multiedit', [
            'entity_ids' => [$this->entity->id],
            'entries' => [
                ['attribute_id' => $this->aid['northing'], 'value' => 5500000],
            ],
        ]);
        $this->assertStatus($response, 204);

        [, $latAfter] = $this->lonLat();
        $this->assertGreaterThan($latBefore + 0.8, $latAfter);
    }

    public function testRemovingSourceRemovesWkt(): void {
        $this->configure();
        $this->patchValues(['easting' => 500000, 'northing' => 5400000, 'srid' => '25832']);
        $this->assertNotNull($this->wkt());

        $this->assertStatus($this->patchValues(['srid' => null], 'remove'), 200);
        $this->assertNull($this->wkt());
    }

    public function testInvalidSridLeavesNoWkt(): void {
        $this->configure();
        $this->patchValues(['easting' => 500000, 'northing' => 5400000, 'srid' => 'not a crs']);
        $this->assertNull($this->wkt());

        $this->patchValues(['srid' => 'EPSG:999999']);
        $this->assertNull($this->wkt());
    }

    public function testConfigureBackfillsExistingValues(): void {
        $this->patchValues(['easting' => 500000, 'northing' => 5400000, 'srid' => '25832']);
        $this->assertNull($this->wkt());

        $response = $this->configure();
        $this->assertStatus($response, 201);
        $this->assertNotNull($this->wkt());

        $source = Source::first();
        $this->userRequest()->post("/api/v1/coordinates/sources/{$source->id}/recompute")
            ->assertStatus(200)
            ->assertJsonStructure(['entities']);
    }

    public function testModeratedChangeOnlyAppliesAfterAccept(): void {
        $this->configure();
        $this->patchValues(['easting' => 500000, 'northing' => 5400000, 'srid' => '25832']);
        [, $latBefore] = $this->lonLat();

        $role = $this->user->roles()->first();
        $role->is_moderated = true;
        $role->save();
        $this->user->unsetRelation('roles');

        $this->assertStatus($this->patchValues(['northing' => 5500000]), 200);
        [, $latPending] = $this->lonLat();
        $this->assertEqualsWithDelta($latBefore, $latPending, 0.0000001);

        $role->is_moderated = false;
        $role->save();
        $this->user->unsetRelation('roles');

        $response = $this->userRequest()->patch(
            "/api/v1/entity/{$this->entity->id}/attribute/{$this->aid['northing']}/moderate",
            ['action' => 'accept']
        );
        $this->assertStatus($response, 204);

        [, $latAccepted] = $this->lonLat();
        $this->assertGreaterThan($latBefore + 0.8, $latAccepted);
    }

    public function testAttributeRemovedEventRemovesMapping(): void {
        $this->configure();
        $this->patchValues(['easting' => 500000, 'northing' => 5400000, 'srid' => '25832']);
        $this->assertNotNull($this->wkt());

        EntityAttribute::where('entity_type_id', $this->entity->entity_type_id)
            ->where('attribute_id', $this->aid['easting'])
            ->first()
            ->removeFromEntityType();
        // Stand-in for the core event entity_type.attribute_removed
        app(GeometrySync::class)->onAttributeRemoved((object) [
            'attributeId' => $this->aid['easting'],
            'entityTypeId' => $this->entity->entity_type_id,
        ]);

        $this->assertEquals(0, Source::count());
        $this->assertFalse($this->wktReadonly());
        $this->assertNotNull($this->wkt());
    }

    public function testRoutesRequireAuthentication(): void {
        $this->unsetTestUser();
        $this->userRequest()->get('/api/v1/coordinates/sources')->assertStatus(401);
    }
}
