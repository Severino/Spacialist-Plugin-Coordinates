<template>
    <div class="dev-component-preview p-5 h-100 d-flex flex-column">
        <h2>Component Preview</h2>
        <div class="flex-grow-1 d-flex align-items-center">
            <RouterView class="flex-grow-1" />
        </div>
    </div>
</template>

<script>
    import { RouterView } from 'vue-router';

    export default {
        components: {
            RouterView,
        },
    };

</script>
