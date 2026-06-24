<!--
* Author: Vika Aqordi
* Created on: 2026-05-05 15:59
* Github: https://github.com/aqordeon
* Copyright: 2026
-->

<script setup lang="ts">
// import { provide } from "vue"
// import { useLayoutStore } from "@/Stores/layout"
import { usePage } from '@inertiajs/vue3'
import { loadLanguageAsync } from 'laravel-vue-i18n'
// import ScreenWarning from "@/Components/Utils/ScreenWarning.vue"

// provide('layout', useLayoutStore())

if (usePage().props.language) {
    loadLanguageAsync(usePage().props.language)
}

// const layout = useLayoutStore()

// console.log('environment:', layout.app.environment)


</script>

<template>
    <div class="relative min-h-[100dvh] w-screen flex items-center justify-center bg-gradient-to-bl from-indigo-400 to-indigo-600 sm:px-6 lg:px-8">
        <slot />
    </div>
</template>
