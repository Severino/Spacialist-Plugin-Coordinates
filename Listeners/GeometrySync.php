<?php

namespace App\Plugins\Coordinates\Listeners;

use App\Attribute;
use App\AttributeValue;
use App\Entity;
use App\Plugins\Coordinates\Attributes\GeometryAttribute;
use App\Plugins\Coordinates\Models\Source;
use App\Plugins\Coordinates\Services\GeometryCalculator;
use Illuminate\Support\Facades\Log;

/**
 * Keeps coordinate geometry values in sync with their easting, northing and SRID values.
 *
 * Registered in plugin.xml under <listeners>. Expected event payloads:
 *  - attribute_value.saved / attribute_value.deleted: $attributeValue (AttributeValue), $user (?User)
 *  - entity_type.attribute_removed: $attributeId (int), $entityTypeId (int)
 */
class GeometrySync
{
    public function __construct(protected GeometryCalculator $calculator) {
    }

    public function onSaved(object $event): void {
        $value = $event->attributeValue ?? null;
        if(!$value instanceof AttributeValue || !$this->isRelevant($value)) {
            return;
        }

        $this->calculator->recomputeForSourceAttribute(
            $value->entity_id,
            $value->attribute_id,
            ($event->user ?? null)?->id ?? $value->user_id
        );
    }

    public function onDeleted(object $event): void {
        $value = $event->attributeValue ?? null;
        if(!$value instanceof AttributeValue || !$this->isRelevant($value)) {
            return;
        }

        // The event may be sent before the row is gone, so leave it out explicitly.
        $this->calculator->recomputeForSourceAttribute(
            $value->entity_id,
            $value->attribute_id,
            ($event->user ?? null)?->id,
            $value->id
        );
    }

    /**
     * A source attribute was removed from an entity type, so its values were
     * deleted in bulk without model events. Recalculate the affected entities.
     */
    public function onAttributeRemoved(object $event): void {
        $attributeId = $event->attributeId ?? null;
        $entityTypeId = $event->entityTypeId ?? null;
        if($attributeId === null || $entityTypeId === null) {
            return;
        }

        $sources = Source::usingSource($attributeId);
        if($sources->isEmpty()) {
            return;
        }

        Entity::where('entity_type_id', $entityTypeId)
            ->select('id')
            ->chunkById(500, function($entities) use ($sources) {
                foreach($entities as $entity) {
                    foreach($sources as $source) {
                        $this->calculator->recompute($entity->id, $source);
                    }
                }
            });
    }

    /**
     * Pending moderation rows are skipped: the value is recalculated when a
     * moderator accepts them. Computed values themselves are skipped to avoid loops.
     */
    private function isRelevant(AttributeValue $value): bool {
        if($value->{$value->getModerationStateColumn()} !== null) {
            return false;
        }

        $datatype = Attribute::whereKey($value->attribute_id)->value('datatype');
        if($datatype === GeometryAttribute::getType()) {
            return false;
        }

        return true;
    }
}
