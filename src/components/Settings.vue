<template>
    <div class="coordinates-settings container-fluid py-3">
        <h4>{{ t('plugin.coordinates.settings.title') }}</h4>
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
                    <th>{{ t('plugin.coordinates.settings.geometry') }}</th>
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
                >
                    <td>{{ label(source.geometry_attribute_id) }}</td>
                    <td>{{ label(source.easting_attribute_id) }}</td>
                    <td>{{ label(source.northing_attribute_id) }}</td>
                    <td>{{ label(source.srid_attribute_id) }}</td>
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
                        colspan="5"
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
                    required
                >
                    <option
                        v-for="attribute in field.options()"
                        :key="attribute.id"
                        :value="attribute.id"
                    >
                        {{ label(attribute.id) }} ({{ attribute.datatype }})
                    </option>
                </select>
            </div>
            <div class="col-md-auto">
                <button
                    type="submit"
                    class="btn btn-primary"
                    :disabled="state.busy"
                >
                    {{ t('plugin.coordinates.settings.save') }}
                </button>
            </div>
        </form>

        <p
            v-if="state.message"
            class="mt-3 text-success"
        >
            {{ state.message }}
        </p>
    </div>
</template>

<script>
    import {
        onMounted,
        reactive,
    } from 'vue';

    const DATATYPE = 'coordinates-geometry';
    const API = 'coordinates/sources';

    export default {
        setup() {
            const t = (key, args) => window.SpPS?.data?.t ? window.SpPS.data.t(key, args) : key;
            const http = (...args) => window.SpPS.api.http(...args);

            const emptyForm = _ => ({
                geometry_attribute_id: null,
                easting_attribute_id: null,
                northing_attribute_id: null,
                srid_attribute_id: null,
            });

            const state = reactive({
                attributes: [],
                sources: [],
                form: emptyForm(),
                busy: false,
                error: null,
                message: null,
            });

            const computedAttributes = _ => state.attributes.filter(a => a.datatype == DATATYPE);
            const sourceAttributes = _ => state.attributes.filter(a => a.datatype != DATATYPE);
            const fields = [
                { key: 'geometry_attribute_id', label: 'geometry', options: computedAttributes },
                { key: 'easting_attribute_id', label: 'easting', options: sourceAttributes },
                { key: 'northing_attribute_id', label: 'northing', options: sourceAttributes },
                { key: 'srid_attribute_id', label: 'srid', options: sourceAttributes },
            ];

            const label = id => {
                const attribute = state.attributes.find(a => a.id == id);
                if(!attribute) return `#${id}`;
                return window.SpPS.api.helpers.translateConcept(attribute.thesaurus_url);
            };

            const run = async (fn) => {
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
                const [attributes, sources] = await Promise.all([
                    http('get', 'editor/dm/attribute'),
                    http('get', API),
                ]);
                state.attributes = attributes.attributes;
                state.sources = sources;
            });

            const save = _ => run(async _ => {
                const source = await http('post', API, state.form);
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
                    geometry_attribute_id: source.geometry_attribute_id,
                    easting_attribute_id: source.easting_attribute_id,
                    northing_attribute_id: source.northing_attribute_id,
                    srid_attribute_id: source.srid_attribute_id,
                };
            };

            const recompute = source => run(async _ => {
                const result = await http('post', `${API}/${source.id}/recompute`);
                state.message = t('plugin.coordinates.settings.recomputed', { count: result.entities });
            });

            const remove = source => run(async _ => {
                await http('delete', `${API}/${source.id}`);
                state.sources = state.sources.filter(s => s.id != source.id);
            });

            onMounted(load);

            return {
                t,
                state,
                fields,
                label,
                save,
                edit,
                recompute,
                remove,
            };
        },
    };
</script>
