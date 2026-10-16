<template>
    <li class="nav-item dropdown">
        <a
            class="nav-link dropdown-toggle"
            href="#"
            role="button"
            data-bs-toggle="dropdown"
            aria-expanded="false"
        >
            {{ title }}<span
                v-if="items.length > 0"
                class="text-secondary ms-1"
            >({{ items.length }})</span>
        </a>
        <ul
            v-if="items.length > 0"
            class="dropdown-menu"
        >
            <li
                v-for="item in items"
                :key="item.label"
            >
                <router-link
                    class="dropdown-item"
                    :to="`/${item.of}/${item.href}`"
                    href="#"
                >
                    {{ item.label }}
                </router-link>
            </li>
        </ul>
    </li>
</template>

<script setup>

    const props = defineProps({
        title: {
            type: String,
            required: true
        },
        items: {
            type: Array,
            required: true
        }
    });
</script>