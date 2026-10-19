<?php

namespace App\Plugins\Coordinates\Controllers;

use App\Attribute;
use App\AttributeValue;
use App\Entity;
use App\EntityAttribute;
use App\Http\Controllers\Controller;
use App\Plugins\Coordinates\Models\Source;
use App\Plugins\Coordinates\Services\GeometryCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SourceController extends Controller
{
    // Defined in permissions.json, the core prefixes it with the group name
    const PERMISSION = 'coordinates_write';

    const WKT_DATATYPES = ['geography'];
    const NUMBER_DATATYPES = ['double', 'integer', 'string'];
    const SRID_DATATYPES = ['string', 'integer'];

    public function __construct(protected GeometryCalculator $calculator) {
    }

    public function index() {
        if($error = $this->denied()) {
            return $error;
        }

        return response()->json(Source::orderBy('id')->get());
    }

    /**
     * Creates the mapping of a WKT attribute, or replaces the one that
     * already exists for this entity type and WKT attribute.
     */
    public function store(Request $request) {
        if($error = $this->denied()) {
            return $error;
        }

        $data = $this->validateMapping($request);
        if(!is_array($data)) {
            return $data;
        }

        $source = Source::where('entity_type_id', $data['entity_type_id'])
            ->where('wkt_attribute_id', $data['wkt_attribute_id'])
            ->firstOrNew();

        return $this->save($source, $data, 201);
    }

    public function update(Request $request, int $id) {
        if($error = $this->denied()) {
            return $error;
        }

        $source = Source::findOrFail($id);
        $data = $this->validateMapping($request);
        if(!is_array($data)) {
            return $data;
        }

        $taken = Source::where('entity_type_id', $data['entity_type_id'])
            ->where('wkt_attribute_id', $data['wkt_attribute_id'])
            ->where('id', '<>', $id)
            ->exists();
        if($taken) {
            return response()->json([
                'error' => __('This WKT attribute is already calculated by another mapping'),
            ], 422);
        }

        return $this->save($source, $data, 200);
    }

    public function recompute(int $id) {
        if($error = $this->denied()) {
            return $error;
        }

        $source = Source::findOrFail($id);
        $count = $this->calculator->recomputeAll($source, auth()->id());

        return response()->json(['entities' => $count]);
    }

    public function destroy(int $id) {
        if($error = $this->denied()) {
            return $error;
        }

        $source = Source::findOrFail($id);
        DB::transaction(function() use ($source) {
            $source->setTargetReadonly(false);
            $source->delete();
        });

        return response()->json(null, 204);
    }

    /**
     * The calculated WKT values of an entity, keyed by attribute id. A value
     * that was removed is null. Used by the frontend to refresh them after a save.
     */
    public function values(int $id) {
        $user = auth()->user();
        if(!$user->can('entity_read') || !$user->can('entity_data_read')) {
            return response()->json([
                'error' => __('You do not have the permission to get an entity\'s data'),
            ], 403);
        }

        $entity = Entity::findOrFail($id);
        $attributeIds = Source::where('entity_type_id', $entity->entity_type_id)
            ->pluck('wkt_attribute_id');

        $values = AttributeValue::whereHas('attribute')
            ->where('entity_id', $entity->id)
            ->whereIn('attribute_id', $attributeIds)
            ->withModerated()
            ->get();
        $data = AttributeValue::generateObject($values);

        $result = [];
        foreach($attributeIds as $attributeId) {
            $result[$attributeId] = $data[$attributeId] ?? null;
        }

        return response()->json((object) $result);
    }

    private function save(Source $source, array $data, int $status) {
        $user = auth()->user();
        DB::transaction(function() use ($source, $data, $user) {
            if($source->exists && ($source->entity_type_id !== $data['entity_type_id'] || $source->wkt_attribute_id !== $data['wkt_attribute_id'])) {
                $source->setTargetReadonly(false);
            }
            $source->fill($data);
            $source->save();
            $source->setTargetReadonly(true);
            $this->calculator->recomputeAll($source, $user->id);
        });

        return response()->json($source->fresh(), $status);
    }

    /**
     * @return array|\Illuminate\Http\JsonResponse the mapping data, or the error response
     */
    private function validateMapping(Request $request) {
        $data = $this->validate($request, [
            'entity_type_id' => 'required|integer|exists:entity_types,id',
            'wkt_attribute_id' => 'required|integer',
            'easting_attribute_id' => 'required|integer|different:wkt_attribute_id|different:northing_attribute_id|different:srid_attribute_id',
            'northing_attribute_id' => 'required|integer|different:wkt_attribute_id|different:srid_attribute_id',
            'srid_attribute_id' => 'required|integer|different:wkt_attribute_id',
        ]);
        $data = array_map('intval', $data);

        $attributeIds = [
            $data['wkt_attribute_id'],
            $data['easting_attribute_id'],
            $data['northing_attribute_id'],
            $data['srid_attribute_id'],
        ];
        $onEntityType = EntityAttribute::where('entity_type_id', $data['entity_type_id'])
            ->whereIn('attribute_id', $attributeIds)
            ->count();
        if($onEntityType !== count($attributeIds)) {
            return response()->json([
                'error' => __('All attributes must belong to the selected entity type'),
            ], 422);
        }

        $datatypes = Attribute::whereIn('id', $attributeIds)->pluck('datatype', 'id');
        $checks = [
            'wkt_attribute_id' => [self::WKT_DATATYPES, __('The target must be a WKT attribute')],
            'easting_attribute_id' => [self::NUMBER_DATATYPES, __('Easting must be a number or text attribute')],
            'northing_attribute_id' => [self::NUMBER_DATATYPES, __('Northing must be a number or text attribute')],
            'srid_attribute_id' => [self::SRID_DATATYPES, __('SRID must be a text or integer attribute')],
        ];
        foreach($checks as $key => [$allowed, $message]) {
            if(!in_array($datatypes[$data[$key]] ?? null, $allowed)) {
                return response()->json(['error' => $message], 422);
            }
        }

        return $data;
    }

    private function denied() {
        if(auth()->user()->can(self::PERMISSION)) {
            return null;
        }

        return response()->json([
            'error' => __('You do not have the permission to change the coordinate settings'),
        ], 403);
    }
}
