<script setup lang="ts">
const props = defineProps<{}>()


import { useIrisLayoutStore } from "@/Stores/irisLayout"
import { provide } from 'vue'
import { initialiseIrisApp } from '@/Composables/initialiseIris'

import { usePage } from "@inertiajs/vue3";


initialiseIrisApp()
const layout = useIrisLayoutStore()
provide('layout', layout)



</script>

<template>
    <div class="relative">
        <slot></slot>
    </div>
</template>
