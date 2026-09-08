<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 07 Sep 2026 12:00:00 Central European Summer Time, Mijas, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import { ref } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faMoneyCheckAlt } from "@fal"
import { trans } from "laravel-vue-i18n"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"

library.add(faMoneyCheckAlt)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    payroll_export_route: { name: string, parameters: object }
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

    <div class="mx-4 mt-6 max-w-xl rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 flex items-end gap-3">
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
</template>
