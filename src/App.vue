<script setup>
    import { RouterView } from 'vue-router';
    import DevHeader from './components/_dev/DevHeader.vue';
</script>

<template>
    <div
        class="d-flex flex-column h-100"
    >
        <DevHeader />
        <div class="flex-fill">
            <RouterView />
        </div>
    </div>
</template>