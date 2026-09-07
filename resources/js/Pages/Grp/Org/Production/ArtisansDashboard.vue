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
    payroll_export_route: { name: string, parameters: object }
    artisans: {
        id: number
        name: string
        assigned: number
        queued: number
        working_now: boolean
    }[]
}>()

function previousMonday(weeksBack: number) {
    const date = new Date()
    const day = (date.getDay() + 6) % 7
    date.setDate(date.getDate() - day - weeksBack * 7)
    return date.toISOString().slice(0, 10)
}
const payrollFrom = ref(previousMonday(1))
const payrollTo = ref((() => {
    const date = new Date(previousMonday(1))
    date.setDate(date.getDate() + 6)
    return date.toISOString().slice(0, 10)
})())

function payrollExportUrl() {
    return route(props.payroll_export_route.name, {
        ...props.payroll_export_route.parameters,
        from: payrollFrom.value,
        to: payrollTo.value,
    })
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
                <span class="font-medium truncate">{{ artisan.name }}</span>
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
            <h2 class="text-lg font-semibold mb-3">{{ trans('Payroll') }}</h2>
            <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 flex items-end gap-3">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">{{ trans('Payroll from') }}</label>
                    <input type="date" v-model="payrollFrom" class="rounded border-gray-300 text-sm" />
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">{{ trans('To') }}</label>
                    <input type="date" v-model="payrollTo" class="rounded border-gray-300 text-sm" />
                </div>
                <a :href="payrollExportUrl()" class="rounded bg-gray-700 text-white text-sm px-3 py-2">
                    {{ trans('Export payroll CSV') }}
                </a>
            </div>
            <Link :href="route(performance_route.name, performance_route.parameters)" class="inline-block mt-3 text-sm text-indigo-700 hover:underline">
                {{ trans('View performance by artisan') }}
            </Link>
        </div>
    </div>
</template>
