<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 09 Nov 2026 16:30:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed } from "vue"
import { Deferred, Link, usePage } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBinoculars } from "@fal"
import { ctrans } from "@/Composables/useTrans"

library.add(faBinoculars)

type CompetitorsSummary = { number_competitors: number; cheaper: number; same: number; dearer: number; to_review: number; failing: number }

const summary = computed(() => usePage().props.competitors as CompetitorsSummary | null | undefined)
const compared = computed(() => (summary.value ? summary.value.cheaper + summary.value.same + summary.value.dearer : 0))
const width = (part: number) => (100 * part) / compared.value + "%"
</script>

<template>
    <Deferred data="competitors">
        <template #fallback>
            <div class="skeleton h-4 w-72 rounded" aria-busy="true" />
        </template>
        <Link v-if="summary" :href="route('grp.masters.competitors.dashboard')" class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500 hover:text-gray-800">
            <span class="flex items-center gap-1 font-medium text-gray-700">
                <FontAwesomeIcon icon="fal fa-binoculars" fixed-width aria-hidden="true" />
                {{ ctrans("Competitors") }} ({{ summary.number_competitors }})
            </span>
            <span v-if="compared" class="flex h-1.5 w-24 overflow-hidden rounded-full bg-gray-100" aria-hidden="true">
                <span class="bg-green-500" :style="{ width: width(summary.cheaper) }" />
                <span class="bg-gray-300" :style="{ width: width(summary.same) }" />
                <span class="bg-red-500" :style="{ width: width(summary.dearer) }" />
            </span>
            <span class="text-green-700">{{ ctrans("We are cheaper") }} {{ summary.cheaper }}</span>
            <span>{{ ctrans("About the same") }} {{ summary.same }}</span>
            <span class="text-red-600">{{ ctrans("We are dearer") }} {{ summary.dearer }}</span>
            <span v-if="summary.to_review">· {{ ctrans(":count to review", { count: summary.to_review }) }}</span>
            <span v-if="summary.failing" class="text-amber-600">· {{ ctrans(":count not read", { count: summary.failing }) }}</span>
        </Link>
    </Deferred>
</template>
