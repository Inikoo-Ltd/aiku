<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 07 Sep 2026 10:00:00 Central European Summer Time, Mijas, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3"
import { ref } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faHatChef, faUserHardHat } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { trans } from "laravel-vue-i18n"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"

library.add(faHatChef, faUserHardHat)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    floor_route: { name: string, parameters: object }
    performance_route: { name: string, parameters: object }
    artisans: {
        id: number
        name: string
        avatar: string | null
        assigned: number
        queued: number
        working_now: boolean
    }[]
}>()

const brokenAvatars = ref(new Set<string>())
function initials(name: string) {
    return name.split(/\s+/).filter(Boolean).slice(0, 2).map(part => part[0]).join('').toUpperCase()
}

</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="mx-4 mt-6 grid gap-6 lg:grid-cols-2">
        <div>
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-lg font-semibold">
                    <FontAwesomeIcon :icon="['fal', 'user-hard-hat']" fixed-width class="text-gray-400 mr-1" />
                    {{ trans('Roster') }}
                </h2>
                <Link :href="route(floor_route.name, floor_route.parameters)" class="rounded bg-indigo-600 text-white text-sm px-3 py-1.5">
                    {{ trans('Open manufacture floor') }}
                </Link>
            </div>

            <div v-if="!artisans.length" class="text-gray-400 text-sm py-6 text-center border border-dashed border-gray-200 rounded-lg">
                {{ trans('No artisans in this factory yet') }}
            </div>
            <div v-for="artisan in artisans" :key="artisan.id"
                class="mb-2 rounded-lg border px-4 py-2 flex items-center justify-between gap-3 text-sm"
                :class="artisan.queued || artisan.assigned ? 'border-gray-200 bg-white' : 'border-amber-300 bg-amber-50'">
                <span class="flex items-center gap-3 min-w-0">
                    <img v-if="artisan.avatar && !brokenAvatars.has(artisan.avatar)" :src="artisan.avatar" :alt="artisan.name" class="h-8 w-8 rounded-full object-cover bg-gray-100" @error="brokenAvatars.add(artisan.avatar)" />
                    <span v-else class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700">{{ initials(artisan.name) }}</span>
                    <span class="font-medium truncate">{{ artisan.name }}</span>
                </span>
                <span class="shrink-0 tabular-nums" :class="artisan.queued || artisan.assigned ? 'text-gray-600' : 'text-amber-700'">
                    <template v-if="artisan.queued || artisan.assigned">
                        <span v-if="artisan.working_now">{{ trans('working') }} · </span>
                        <span v-if="artisan.assigned">{{ artisan.assigned }} {{ trans('assigned') }}</span>
                        <span v-if="artisan.assigned && artisan.queued"> · </span>
                        <span v-if="artisan.queued">{{ artisan.queued }} {{ trans('on floor') }}</span>
                    </template>
                    <template v-else>{{ trans('nothing queued') }}</template>
                </span>
            </div>
        </div>

        <div>
            <Link :href="route(performance_route.name, performance_route.parameters)" class="inline-block mt-3 text-sm text-indigo-700 hover:underline">
                {{ trans('View performance by artisan') }}
            </Link>
        </div>
    </div>
</template>
