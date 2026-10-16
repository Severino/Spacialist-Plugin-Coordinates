<?php

namespace App\Plugins\Coordinates\Controllers;

use App\Attribute;
use App\Http\Controllers\Controller;
use App\Plugins\Coordinates\Attributes\GeometryAttribute;
use App\Plugins\Coordinates\Models\Source;
use App\Plugins\Coordinates\Services\GeometryCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SourceController extends Controller
{
    public function __construct(protected GeometryCalculator $calculator) {
    }

    public function index() {
        if(!auth()->user()->can('attribute_read')) {
            return response()->json([
                'error' => __('You do not have the permission to view attributes'),
            ], 403);
        }

        return response()->json(Source::orderBy('id')->get());
    }

    /**
     * Creates or replaces the mapping of a coordinate geometry attribute
     * and recalculates all its values.
     */
    public function store(Request $request) {
        $user = auth()->user();
        if(!$user->can('attribute_write')) {
            return response()->json([
                'error' => __('You do not have the permission to modify attributes'),
            ], 403);
        }

        $this->validate($request, [
            'geometry_attribute_id' => 'required|integer|exists:attributes,id',
            'easting_attribute_id' => 'required|integer|exists:attributes,id|different:northing_attribute_id|different:srid_attribute_id',
            'northing_attribute_id' => 'required|integer|exists:attributes,id|different:srid_attribute_id',
            'srid_attribute_id' => 'required|integer|exists:attributes,id',
        ]);

        $geometryAttribute = Attribute::find($request->get('geometry_attribute_id'));
        if($geometryAttribute->datatype !== GeometryAttribute::getType()) {
            return response()->json([
                'error' => __('The target attribute must be a coordinate geometry attribute'),
            ], 422);
        }
        $sourceIds = $request->only(['easting_attribute_id', 'northing_attribute_id', 'srid_attribute_id']);
        $usesComputed = Attribute::whereIn('id', $sourceIds)
            ->where('datatype', GeometryAttribute::getType())
            ->exists();
        if($usesComputed) {
            return response()->json([
                'error' => __('A coordinate geometry attribute cannot be used as easting, northing or SRID'),
            ], 422);
        }

        $source = DB::transaction(function() use ($geometryAttribute, $sourceIds, $user) {
            $source = Source::updateOrCreate(
                ['geometry_attribute_id' => $geometryAttribute->id],
                $sourceIds
            );
            $this->calculator->recomputeAll($source, $user->id);
            return $source;
        });

        return response()->json($source, 201);
    }

    public function recompute(int $id) {
        $user = auth()->user();
        if(!$user->can('attribute_write')) {
            return response()->json([
                'error' => __('You do not have the permission to modify attributes'),
            ], 403);
        }

        $source = Source::findOrFail($id);
        $count = $this->calculator->recomputeAll($source, $user->id);

        return response()->json(['entities' => $count]);
    }

    public function destroy(int $id) {
        if(!auth()->user()->can('attribute_write')) {
            return response()->json([
                'error' => __('You do not have the permission to modify attributes'),
            ], 403);
        }

        Source::findOrFail($id)->delete();

        return response()->json(null, 204);
    }
}
