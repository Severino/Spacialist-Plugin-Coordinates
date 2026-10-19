<template>
    <div class="coordinates-settings container-fluid py-3">
        <h4>{{ t('plugin.coordinates.settings.title') }}</h4>

        <div
            v-if="!allowed"
            class="alert alert-warning"
        >
            {{ t('plugin.coordinates.settings.no_permission') }}
        </div>
        <template v-else>
            <p class="text-muted">
                {{ t('plugin.coordinates.settings.description') }}
            </p>

            <div
                v-if="state.error"
                class="alert alert-danger"
            >
                {{ state.error }}
            </div>

            <table class="table table-sm align-middle">
                <thead>
                    <tr>
                        <th>{{ t('plugin.coordinates.settings.entity_type') }}</th>
                        <th>{{ t('plugin.coordinates.settings.wkt') }}</th>
                        <th>{{ t('plugin.coordinates.settings.easting') }}</th>
                        <th>{{ t('plugin.coordinates.settings.northing') }}</th>
                        <th>{{ t('plugin.coordinates.settings.srid') }}</th>
                        <th />
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="source in state.sources"
                        :key="source.id"
                        :class="{ 'table-active': state.form.id == source.id }"
                    >
                        <td>{{ entityTypeLabel(source.entity_type_id) }}</td>
                        <td>{{ attributeLabel(source.entity_type_id, source.wkt_attribute_id) }}</td>
                        <td>{{ attributeLabel(source.entity_type_id, source.easting_attribute_id) }}</td>
                        <td>{{ attributeLabel(source.entity_type_id, source.northing_attribute_id) }}</td>
                        <td>{{ attributeLabel(source.entity_type_id, source.srid_attribute_id) }}</td>
                        <td class="text-end text-nowrap">
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-secondary me-1"
                                :disabled="state.busy"
                                @click="edit(source)"
                            >
                                <i class="fas fa-fw fa-edit" />
                            </button>
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-secondary me-1"
                                :title="t('plugin.coordinates.settings.recompute')"
                                :disabled="state.busy"
                                @click="recompute(source)"
                            >
                                <i class="fas fa-fw fa-sync" />
                            </button>
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-danger"
                                :disabled="state.busy"
                                @click="remove(source)"
                            >
                                <i class="fas fa-fw fa-trash" />
                            </button>
                        </td>
                    </tr>
                    <tr v-if="state.sources.length == 0">
                        <td
                            colspan="6"
                            class="text-muted"
                        >
                            {{ t('plugin.coordinates.settings.none') }}
                        </td>
                    </tr>
                </tbody>
            </table>

            <form
                class="row g-2 align-items-end"
                @submit.prevent="save"
            >
                <div class="col-md">
                    <label
                        class="form-label"
                        for="coordinates-entity_type_id"
                    >
                        {{ t('plugin.coordinates.settings.entity_type') }}
                    </label>
                    <select
                        id="coordinates-entity_type_id"
                        v-model="state.form.entity_type_id"
                        class="form-select"
                        required
                        @change="resetAttributes"
                    >
                        <option
                            v-for="entityType in entityTypes"
                            :key="entityType.id"
                            :value="entityType.id"
                        >
                            {{ entityTypeLabel(entityType.id) }}
                        </option>
                    </select>
                </div>
                <div
                    v-for="field in fields"
                    :key="field.key"
                    class="col-md"
                >
                    <label
                        class="form-label"
                        :for="`coordinates-${field.key}`"
                    >
                        {{ t(`plugin.coordinates.settings.${field.label}`) }}
                    </label>
                    <select
                        :id="`coordinates-${field.key}`"
                        v-model="state.form[field.key]"
                        class="form-select"
                        :disabled="!state.form.entity_type_id"
                        required
                    >
                        <option
                            v-for="attribute in options(field.datatypes)"
                            :key="attribute.id"
                            :value="attribute.id"
                        >
                            {{ attributeLabel(state.form.entity_type_id, attribute.id) }} ({{ attribute.datatype }})
                        </option>
                    </select>
                </div>
                <div class="col-md-auto">
                    <button
                        v-if="state.form.id"
                        type="button"
                        class="btn btn-outline-secondary me-1"
                        :disabled="state.busy"
                        @click="cancel"
                    >
                        {{ t('plugin.coordinates.settings.cancel') }}
                    </button>
                    <button
                        type="submit"
                        class="btn btn-primary"
                        :disabled="state.busy"
                    >
                        {{ t('plugin.coordinates.settings.save') }}
                    </button>
                </div>
            </form>
            <p class="form-text">
                {{ t('plugin.coordinates.settings.overwrite_hint') }}
            </p>

            <p
                v-if="state.message"
                class="mt-3 text-success"
            >
                {{ state.message }}
            </p>
        </template>
    </div>
</template>

<script>
    import {
        computed,
        onMounted,
        reactive,
    } from 'vue';

    const API = 'coordinates/sources';
    // Same lists as in SourceController
    const WKT_DATATYPES = ['geography'];
    const NUMBER_DATATYPES = ['double', 'integer', 'string'];
    const SRID_DATATYPES = ['string', 'integer'];

    export default {
        setup() {
            const t = (key, args) => window.SpPS?.data?.t ? window.SpPS.data.t(key, args) : key;
            const http = (...args) => window.SpPS.api.http(...args);
            const entityStore = window.SpPS.api.store.entityStore;

            const allowed = computed(_ => window.SpPS.api.helpers.can('coordinates_write'));

            const emptyForm = _ => ({
                id: null,
                entity_type_id: null,
                wkt_attribute_id: null,
                easting_attribute_id: null,
                northing_attribute_id: null,
                srid_attribute_id: null,
            });

            const state = reactive({
                sources: [],
                form: emptyForm(),
                busy: false,
                error: null,
                message: null,
            });

            const fields = [
                { key: 'wkt_attribute_id', label: 'wkt', datatypes: WKT_DATATYPES },
                { key: 'easting_attribute_id', label: 'easting', datatypes: NUMBER_DATATYPES },
                { key: 'northing_attribute_id', label: 'northing', datatypes: NUMBER_DATATYPES },
                { key: 'srid_attribute_id', label: 'srid', datatypes: SRID_DATATYPES },
            ];

            const entityTypes = computed(_ => Object.values(entityStore.entityTypes));
            const options = datatypes => {
                return entityStore.getEntityTypeAttributes(state.form.entity_type_id)
                    .filter(a => datatypes.includes(a.datatype));
            };

            const entityTypeLabel = id => entityStore.getEntityTypeName(id) || `#${id}`;
            const attributeLabel = (entityTypeId, id) => {
                const attribute = entityStore.getEntityTypeAttributes(entityTypeId).find(a => a.id == id);
                if(!attribute) return `#${id}`;
                return window.SpPS.api.helpers.translateConcept(attribute.thesaurus_url);
            };

            const run = async fn => {
                state.busy = true;
                state.error = null;
                state.message = null;
                try {
                    await fn();
                } catch(e) {
                    state.error = e?.response?.data?.error ?? e?.response?.data?.message ?? e.message;
                } finally {
                    state.busy = false;
                }
            };

            const load = _ => run(async _ => {
                state.sources = await http('get', API);
            });

            const resetAttributes = _ => {
                fields.forEach(field => state.form[field.key] = null);
            };

            const save = _ => run(async _ => {
                const { id, ...data } = state.form;
                const source = id ? await http('put', `${API}/${id}`, data) : await http('post', API, data);
                const idx = state.sources.findIndex(s => s.id == source.id);
                if(idx > -1) {
                    state.sources.splice(idx, 1, source);
                } else {
                    state.sources.push(source);
                }
                state.form = emptyForm();
                state.message = t('plugin.coordinates.settings.saved');
            });

            const edit = source => {
                state.form = {
                    id: source.id,
                    entity_type_id: source.entity_type_id,
                    wkt_attribute_id: source.wkt_attribute_id,
                    easting_attribute_id: source.easting_attribute_id,
                    northing_attribute_id: source.northing_attribute_id,
                    srid_attribute_id: source.srid_attribute_id,
                };
            };

            const cancel = _ => {
                state.form = emptyForm();
            };

            const recompute = source => run(async _ => {
                const result = await http('post', `${API}/${source.id}/recompute`);
                state.message = t('plugin.coordinates.settings.recomputed', { count: result.entities });
            });

            const remove = source => run(async _ => {
                await http('delete', `${API}/${source.id}`);
                state.sources = state.sources.filter(s => s.id != source.id);
                if(state.form.id == source.id) {
                    state.form = emptyForm();
                }
            });

            onMounted(_ => {
                if(allowed.value) load();
            });

            return {
                t,
                allowed,
                state,
                fields,
                entityTypes,
                options,
                entityTypeLabel,
                attributeLabel,
                resetAttributes,
                save,
                edit,
                cancel,
                recompute,
                remove,
            };
        },
    };
</script>
