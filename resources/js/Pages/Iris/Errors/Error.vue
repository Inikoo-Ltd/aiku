<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 27 Sept 2024 00:53:11 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
// import Button from '@/Components/Elements/Buttons/Button.vue'
import { layoutStructure } from '@/Composables/useLayoutStructure'
// import { Button as ButtonTS } from '@/types/Button'
// import { routeType } from '@/types/route'
// import { Link } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'
import { inject } from 'vue'
import { ufo404 } from '@/Assets/Iconscout/404_ufo'
import { rocket_falling } from '@/Assets/Iconscout/rocket_falling'

const props = defineProps<{
    status: number
    title: string
    description: string
}>()

const layout = inject('layout', layoutStructure)


</script>

<template>
    <main class="grid min-h-full place-items-center bg-white px-6 py-10 sm:py-16 lg:px-8">
        <div class="flex flex-col sm:flex-row gap-x-12 justify-start items-center">
            <div class="w-64 mx-auto" :style="{ color: layout?.app?.theme?.[0] }"
                v-html="status == 404 ? ufo404 : rocket_falling"></div>

            <div class="flex flex-col items-center sm:items-start">
                <p class="text-xl font-semibold " :style="{ color: layout?.app?.theme?.[0] }">
                    {{ status || 404 }}
                </p>
                <h1 class="mt-4 text-3xl font-bold tracking-tight sm:text-5xl">
                    {{ title || (status == 404 ? trans('Page not found') : trans('Something went wrong')) }}
                </h1>
                <p class="text-center sm:text-left mt-4 text-base leading-7 text-gray-400">
                    {{ description || trans('Sorry, we could not find the page you’re looking for.') }}
                </p>
            </div>
        </div>
    </main>
</template>