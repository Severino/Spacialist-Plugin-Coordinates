import { defineStore } from 'pinia';

export const useAppStore = defineStore('appStore', {
    state: () => ({
        activeTab: null,
        components: [],
        tabs: [],
        settings: [],
        tools: [],
        preferences: [],
    }),
    actions: {
        setActiveTab(tab) {
            this.activeTab = tab;
        },
        addTab(tab) {
            if(this.tabs.length == 0) {
                this.activeTab = tab;
            }
            this.tabs.push(tab);
        },
        registerComponent(obj) {
            this.components.push(obj);
        }
    },
});