<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 26 Sept 2024 22:04:10 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ufo404 } from '@/Assets/Iconscout/404_ufo'
import { rocket_falling } from '@/Assets/Iconscout/rocket_falling'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import { Button as ButtonTS } from '@/types/Button'
import { routeType } from '@/types/route'
import { Link } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'
import { inject } from 'vue'

const props = defineProps<{
    status: {}
}>()

const layout = inject('layout', layoutStructure)

</script>

<template>
    <main class="grid min-h-full place-items-center bg-white px-6 py-24 sm:py-32 lg:px-8">
        <div class="flex gap-x-12 items-center">

            <div class="w-64 mx-auto" :style="{color: layout?.app?.theme?.[0]}" v-html="status == 404 ? ufo404 : rocket_falling"></div>
            
            <div>
                <p class="text-xl font-semibold " :style="{color: layout?.app?.theme?.[0]}">
                    {{ status || 404 }}
                </p>
                <h1 class="mt-4 text-3xl font-bold tracking-tight sm:text-5xl">
                    {{ status == 404 ? trans('Page not found') : trans('Something went wrong') }}
                </h1>
                <p class="mt-4 text-base leading-7 text-gray-400">
                    {{ trans('Sorry, we could not find the page you’re looking for.') }}
                </p>
            </div>
        </div>
    </main>
</template>