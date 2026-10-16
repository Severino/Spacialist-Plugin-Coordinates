<template>
    <div
        v-if="store.tabs.length === 0"
        class="alert alert-secondary m-3"
        role="alert"
    >
        No Tabs registered in Plugin
    </div>
    <ul
        v-else
        class="nav nav-pills gap-2 p-3"
    >
        <li
            v-for="(tab, index) in store.tabs"
            :key="index"
            class="nav-item"
        >
            <a
                class="nav-link"
                :class="pillClass(tab)"
                aria-current="page"
                href="#"
                @click.prevent="() => store.setActiveTab(tab)"
            >{{ tab.label }}</a>
        </li>
    </ul>
    <component
        :is="store.activeTab.component"
        v-if="store.activeTab"
        v-bind="store.activeTab.props"
        class="p-3"
    />
</template>

<script>
    import { computed } from 'vue';
    import { useAppStore } from '../../js/_dev/store';

    export default {
        setup() {

            const store = useAppStore();

            const pillClass = computed(() => {
                return (tab) => {
                    return {
                        'active': tab.label === store.activeTab.label,
                    };
                };
            });

            return {
                store,
                pillClass,
            };
        }
    };
</script>

<style lang='scss' scoped></style>