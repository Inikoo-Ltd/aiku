<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 04 Apr 2023 08:47:34 Malaysia Time, Sanur, Bali, Indonesia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { usePage } from '@inertiajs/vue3'
import { loadLanguageAsync } from 'laravel-vue-i18n'
import { provide } from "vue"
import { useLayoutStore } from "@/Stores/retinaLayout"
import ScreenWarning from '@/Components/Utils/ScreenWarning.vue'
import { faExclamationTriangle } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
import Image from '@/Components/Image.vue'
library.add(faExclamationTriangle)


provide('layout', useLayoutStore())

if (usePage().props.language) {
    loadLanguageAsync(usePage().props?.language)
}

const isStaging = useLayoutStore().app.environment === 'staging'

</script>

<template>
    <div class="relative h-screen w-screen bg-gradient-to-tr from-slate-950 to-slate-800 flex items-center justify-center sm:px-6 lg:px-8">
        <div class="w-full -mt-12">
            <div v-if="!usePage().props?.iris?.website?.logo" class="flex items-center justify-center gap-x-2">
                <img class="h-12 w-auto" src="/art/logo-yellow.svg" :alt="usePage().props.iris?.website?.name || 'App'" />
                <span style="font-family: Fira" class="text-4xl text-white leading-none">{{ usePage().props.iris?.website?.name }}</span>
            </div>
            <div v-else class="flex items-center justify-center gap-x-2">
                <Image class="h-12 w-auto" :src="usePage().props?.iris?.website?.logo " :alt="usePage().props.iris?.name || 'App'" />
            </div>

            <div class="grid grid-cols-7 mt-8 mx-auto md:w-full max-w-xl shadow-lg rounded-lg overflow-hidden">
                <ScreenWarning v-if="isStaging" class="col-span-7 relative my-4" />
                <div class="col-span-7 backdrop-blur-sm relative bg-white py-8 px-4 md:px-10">
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
