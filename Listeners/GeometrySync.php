<?php

namespace App\Plugins\Coordinates\Listeners;

use App\AttributeValue;
use App\Plugins\Coordinates\Models\Source;
use App\Plugins\Coordinates\Services\GeometryCalculator;

/**
 * Keeps WKT values in sync with their easting, northing and SRID values.
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
        if(!$value instanceof AttributeValue || $this->isPending($value)) {
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
        if(!$value instanceof AttributeValue || $this->isPending($value)) {
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
     * An attribute of a mapping was removed from its entity type, so the
     * mapping can't work anymore. It is removed and the WKT attribute
     * becomes editable again; existing values are kept.
     */
    public function onAttributeRemoved(object $event): void {
        $attributeId = $event->attributeId ?? null;
        $entityTypeId = $event->entityTypeId ?? null;
        if($attributeId === null || $entityTypeId === null) {
            return;
        }

        $sources = Source::where('entity_type_id', $entityTypeId)->get()
            ->filter(fn($source) => in_array($attributeId, $source->attributeIds()));
        foreach($sources as $source) {
            $source->setTargetReadonly(false);
            $source->delete();
        }
    }

    /**
     * Pending moderation rows are skipped: the value is recalculated when a
     * moderator accepts them.
     */
    private function isPending(AttributeValue $value): bool {
        return $value->{$value->getModerationStateColumn()} !== null;
    }
}
