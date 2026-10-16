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
        reactive,
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
        setup() {
            const t = (key, args) => window.SpPS?.data?.t ? window.SpPS.data.t(key, args) : key;

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
