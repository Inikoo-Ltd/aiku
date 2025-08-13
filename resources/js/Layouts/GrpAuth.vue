<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 04 Apr 2023 08:47:34 Malaysia Time, Sanur, Bali, Indonesia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { provide } from "vue"
import { useLayoutStore } from "@/Stores/layout"

import { usePage } from '@inertiajs/vue3'
import { loadLanguageAsync } from 'laravel-vue-i18n'
import { breakpointType } from '@/Composables/useWindowSize'
import ScreenWarning from "@/Components/Utils/ScreenWarning.vue"

provide('layout', useLayoutStore())

if (usePage().props.language) {
    loadLanguageAsync(usePage().props.language)
}

const layout = useLayoutStore()

console.log('environment:', useLayoutStore().app.environment)


</script>

<template>
    <ScreenWarning v-if="layout.app.environment === 'staging'" />
    <div :style="{'background-image': `${'url(/art/background-guest.webp'}`, 'background-repeat': 'no-repeat', 'background-size': 'cover', 'background-position': 'center'}"
        class="relative h-screen w-screen flex items-center justify-center bg-gradient-to-bl from-indigo-400 to-indigo-600 sm:px-6 lg:px-8">
        <div class="absolute bottom-5 left-10 flex items-center justify-center gap-x-2">
            <img class="h-12 w-auto" src="/art/logo-yellow.svg" alt="Aiku" />
            <span style="font-family: Fira, sans-serif" class="text-4xl text-white leading-none">aiku</span>
        </div>

        <div class="mt-8 mx-auto md:w-full max-w-md">
            <div class="relative bg-white/65 py-8 px-4 shadow rounded-lg md:px-10">
                <slot />
            </div>
        </div>
    </div>
</template>
