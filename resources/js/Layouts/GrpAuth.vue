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
import ScreenWarning from "@/Components/Utils/ScreenWarning.vue"
import { watercolourBackground } from "@/Composables/useWatercolourBackground"
import { dailyLine } from "@/Composables/useDailyLine"
import { Head } from "@inertiajs/vue3"

provide('layout', useLayoutStore())

if (usePage().props.language) {
    loadLanguageAsync(usePage().props.language)
}

const layout = useLayoutStore()
const publicSiteUrl = typeof window !== 'undefined' ? `${window.location.protocol}//${window.location.hostname.replace(/^app\./, '')}` : '/'


console.log('environment:', useLayoutStore().app.environment)


</script>

<template>
    <ScreenWarning v-if="layout.app.environment === 'staging'" />
    <div :style="{'background-image': watercolourBackground(), 'background-size': 'cover', 'background-position': 'center'}"
          class="relative min-h-[100dvh] w-screen flex items-center justify-center bg-[#fbf7ee] sm:px-6 lg:px-8">
        <Head><link rel="stylesheet" href="https://fonts.bunny.net/css?family=caveat:400&display=swap" /></Head>
        <div class="absolute top-9 right-10 max-w-xs text-right select-none hidden sm:block" style="font-family: Caveat, 'Segoe Print', 'Bradley Hand', cursive" aria-hidden="true">
            <span class="text-2xl leading-tight text-[#1c1b22]/70">{{ dailyLine() }}</span>
        </div>
        <a :href="publicSiteUrl" class="absolute top-8 left-10 flex items-center gap-x-3 select-none hover:opacity-80" title="About aiku">
            <img class="h-14 w-auto" src="/art/logo-sketch.svg" alt="aiku" />
            <span style="font-family: Georgia, 'Times New Roman', serif" class="text-4xl font-semibold text-[#1c1b22] leading-none tracking-tight">aiku</span>
        </a>

        <div class="mt-8 mx-auto md:w-full max-w-md">
            <div class="relative bg-white/80 backdrop-blur-sm py-8 px-4 rounded-lg md:px-10 border border-[#1c1b22]/10 shadow-[0_8px_30px_rgba(28,27,34,0.08)]">
                <slot />
            </div>
        </div>
    </div>
</template>
