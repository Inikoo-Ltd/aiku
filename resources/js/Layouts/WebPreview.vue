<script setup lang="ts">
const props = defineProps<{}>()

import { initialiseApp } from "@/Composables/initialiseApp"
import { useLayoutStore } from "@/Stores/layout"
import { provide } from 'vue'


initialiseApp()
provide('layout', useLayoutStore())
</script>

<template>
    <div class="relative">
        <slot></slot>
    </div>
</template>
