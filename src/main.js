import GeometryAttribute from './components/GeometryAttribute.vue';
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

SpPS.registerComponent({
    of,
    type: 'attribute',
    key: id,
    datatype: 'coordinates-geometry',
    component: GeometryAttribute,
});
