<template>
    <div class="coordinates-geometry-attribute">
        <div class="input-group">
            <span
                class="input-group-text"
                :title="t('plugin.coordinates.readonly_hint')"
            >
                <i class="fas fa-fw fa-calculator" />
            </span>
            <input
                class="form-control font-monospace"
                type="text"
                readonly
                :name="name"
                :value="value || ''"
                :placeholder="t('plugin.coordinates.empty')"
            >
        </div>
        <small class="text-muted">
            {{ t('plugin.coordinates.readonly_hint') }}
        </small>
    </div>
</template>

<script>
    import {
        computed,
        reactive,
        watch,
    } from 'vue';

    export default {
        props: {
            value: {
                type: String,
                required: false,
                default: '',
            },
            name: {
                type: String,
                required: true,
            },
            disabled: {
                type: Boolean,
                required: false,
                default: false,
            },
        },
        // The value is calculated by the backend, so this component never emits 'change'.
        emits: ['change'],
        setup(props) {
            const t = (key, args) => window.SpPS?.data?.t ? window.SpPS.data.t(key, args) : key;
            const entityStore = window.SpPS.api.store.entityStore;

            // Attribute.vue passes the attribute id only as name="attr-{id}"
            const attributeId = parseInt(props.name.replace('attr-', ''));
            const entity = computed(_ => entityStore.selectedEntity);

            // The core only updates the values the user edited, but the backend
            // recalculates this one on every save. Every save changes updated_at,
            // so reload this value whenever that happens on the same entity.
            const refresh = async entityId => {
                const data = await window.SpPS.api.http('get', `entity/${entityId}/data/${attributeId}`);
                if(entity.value?.id != entityId || !entity.value.data) return;
                entity.value.data[attributeId] = data?.[attributeId] ?? {};
            };
            watch(
                _ => [entity.value?.id, entity.value?.updated_at],
                ([id, updatedAt], [oldId, oldUpdatedAt] = []) => {
                    if(!id || id != oldId || updatedAt == oldUpdatedAt) return;
                    refresh(id).catch(e => console.error('Coordinates: could not reload geometry', e));
                }
            );

            // Attribute.vue reads `v.meta` and calls these on every attribute component
            const v = reactive({
                meta: {
                    dirty: false,
                    valid: true,
                    validated: true,
                },
            });
            const resetFieldState = _ => {};
            const undirtyField = _ => {};

            return {
                t,
                v,
                resetFieldState,
                undirtyField,
            };
        },
    };
</script>
