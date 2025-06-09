<script setup lang="ts">
const props = defineProps<{}>()

import { initialiseApp } from "@/Composables/initialiseApp"
import { irisStyleVariables } from "@/Composables/Workshop"
import { useLayoutStore } from "@/Stores/layout"
import { onMounted, provide } from 'vue'

const layout = useLayoutStore()

initialiseApp()
provide('layout', layout)

onMounted(() => {
    irisStyleVariables(layout?.app?.theme?.color)
})
</script>

<template>
    <div class="relative">
        <slot></slot>
    </div>
</template>
