import { watch } from 'vue';

import Settings from './components/Settings.vue';

import * as en from './i18n/en.json';
import * as de from './i18n/de.json';

// kebab-case of the plugin name in plugin.xml
const id = 'coordinates';
const of = id;

SpPS.register({
    id,
    i18n: {
        en: { [id]: en.default },
        de: { [id]: de.default },
    },
});

SpPS.registerRoutes(id, [{
    path: 'settings',
    component: Settings,
}]);

SpPS.intoSlot({
    of,
    key: 'coordinates-settings',
    slot: 'settings',
    icon: 'fa-map-marker-alt',
    label: 'plugin.coordinates.settings.title',
    href: 'settings',
});

// The core only updates the values the user edited, but the backend
// recalculates the WKT values on every save. Every save changes updated_at,
// so reload them whenever that happens on the selected entity.
// Can be removed once the core returns the saved values in the PATCH response.
const entityStore = SpPS.api.store.entityStore;
const reloadValues = async entityId => {
    const values = await SpPS.api.http('get', `${id}/entity/${entityId}/values`);
    const entity = entityStore.selectedEntity;
    if(entity?.id != entityId || !entity.data) return;
    for(const [attributeId, value] of Object.entries(values ?? {})) {
        entity.data[attributeId] = value ?? {};
    }
};
watch(
    _ => [entityStore.selectedEntity?.id, entityStore.selectedEntity?.updated_at],
    ([entityId, updatedAt], [oldEntityId, oldUpdatedAt] = []) => {
        if(!entityId || entityId != oldEntityId || updatedAt == oldUpdatedAt) return;
        reloadValues(entityId).catch(e => console.error('Coordinates: could not reload WKT values', e));
    }
);
