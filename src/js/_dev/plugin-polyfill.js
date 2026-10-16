import DevComponentPreview from '../../components/_dev/DevComponentPreview.vue';
import router from './router';
import { useAppStore } from './store';
/** 
 * Polyfill for the SpPS plugin system.
 */
export function createPluginPolyfill() {
    
    let componentRouteAdded = false;
    let componentRoute = {
        path: `/component`,
        name: 'component',
        component: DevComponentPreview,
        children: []
    };

    window.SpPS = {
        register: ({ id, i18n, routes, store }) => { },
        registerI18n: (id, i18n) => { },
        registerComponent: (componentDefinition) => {
            const store = useAppStore();
            store.registerComponent(componentDefinition);

            if(!componentRouteAdded){
                router.addRoute(componentRoute);
                componentRouteAdded = true;
            }

            const path = componentDefinition.componentTag ?? componentDefinition.key;
            router.addRoute('component', {
                path: `${path}`,
                component: componentDefinition.component
            });
        },
        registerRoutes: (id, routes) => {
            const pluginRoute = {
                path: `/${id}`,
                name: id,
                component: null,
                children: [],
                meta: {
                    auth: true
                }
            };
            routes.forEach(route => {
                if(!route.component) {
                    console.error(`Route ${route.path} does not have a component.`);
                    return;
                }

                pluginRoute.children.push({
                    path: route.path,
                    component: route.component,
                    name: `${id}_${route.path.replaceAll('/', '_')}`,
                    children: route.children,
                    params: route.params,
                    props: route.props,
                });
            });
            router.addRoute(pluginRoute);
        },
        intoSlot: ({
            of, // unique id string of the plugin.
            slot, // ["tab","tools","settings"] - unique slot string of the plugin.
            component, // component of the slot. Requires componentTag to be set.
            componentTag, // tag of the component, defaults to key.
            key, // unique key string of the slot.
            icon, // icon of the slot.
            label, // label of the slot.
            href, // Unknown at the moment.
            props, // Currently Unsupported
        }) => {
            const store = useAppStore();

            if(slot == 'tab') {
                const tab = {
                    id: key,
                    of: of,
                    icon: icon,
                    label: label,
                    component: component,
                    componentTag: componentTag,
                    href: href ?? '',
                    props: props,
                };
                store.addTab(tab);
            } else if(slot == 'tools' || slot == 'settings') {
                const item = {
                    id: key,
                    of: of,
                    icon: icon,
                    label: label,
                    component: component,
                    componentTag: componentTag,
                    href: href ?? '',
                };
                store[slot].push(item);
            } else {
                console.error(`Unknown slot type: ${slot}`);
            }
        },
    };
}