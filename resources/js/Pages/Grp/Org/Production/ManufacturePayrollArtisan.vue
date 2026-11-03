<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 20 Oct 2026 22:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faMoneyCheckAlt, faArrowLeft } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { ctrans } from "@/Composables/useTrans"
import { capitalize } from "@/Composables/capitalize"
import { useFormatTime } from "@/Composables/useFormatTime"
import { PageHeadingTypes } from "@/types/PageHeading"

library.add(faMoneyCheckAlt, faArrowLeft)

defineProps<{
    title: string
    pageHead: PageHeadingTypes
    period: { from: string, to: string }
    back_route: { name: string, parameters: object }
    days: object
    data: object
}>()

const payKeys = ["working", "band_0", "band_1", "band_2", "band_3", "band_d", "band_dg", "guru"]
const tabletKeys = ["tablet_hours", "not_on_tablet_hours"]
const money = (amount: number) => `£${amount.toFixed(2)}`
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #other>
            <Link :href="route(back_route.name, back_route.parameters)">
                <Button type="tertiary" :label="ctrans('Payroll')">
                    <template #icon><FontAwesomeIcon :icon="['fal', 'arrow-left']" fixed-width aria-hidden="true" /></template>
                </Button>
            </Link>
        </template>
    </PageHeading>

    <div class="mx-4 mt-4 text-sm text-gray-500">{{ useFormatTime(period.from) }} – {{ useFormatTime(period.to) }}</div>

    <Table :resource="days" name="days" class="mt-2">
        <template #cell(date)="{ item }">
            <div class="flex items-center gap-2 whitespace-nowrap">
                {{ useFormatTime(item.date) }}
                <span v-if="!item.is_clocked" class="rounded bg-red-100 px-1.5 py-0.5 text-xs font-medium text-red-800" v-tooltip="ctrans('No clocking on this day: only clocked time is paid')">{{ ctrans('Not clocked') }}</span>
                <span v-else-if="item.open_clockings" class="rounded bg-red-100 px-1.5 py-0.5 text-xs font-medium text-red-800" v-tooltip="ctrans('Clocked in and not clocked out: correct the clocking in Timesheets')">{{ ctrans('Not clocked out') }}</span>
                <span v-if="item.tablet_over_clocked_hours" class="rounded bg-red-100 px-1.5 py-0.5 text-xs font-medium text-red-800" v-tooltip="ctrans('More time on the tablet than clocked: only the clocked time is paid, taken off the jobs that ended last')">
                    {{ ctrans('Tablet over clocked :hours h', { hours: item.tablet_over_clocked_hours.toFixed(2) }) }}
                </span>
            </div>
        </template>
        <template v-for="key in payKeys" :key="key" #[`cell(${key})`]="{ item }">
            <div class="grid grid-cols-[3.5rem_4.5rem] justify-end gap-2 tabular-nums whitespace-nowrap">
                <span class="text-right text-gray-400">{{ item[`${key}_hours`].toFixed(2) }}</span>
                <span class="text-right">{{ money(item[`${key}_amount`]) }}</span>
            </div>
        </template>
        <template v-for="key in tabletKeys" :key="key" #[`cell(${key})`]="{ item }">
            <span class="tabular-nums text-gray-500">{{ item[key].toFixed(2) }}</span>
        </template>
        <template #cell(total)="{ item }">
            <span class="font-semibold tabular-nums">{{ money(item.total) }}</span>
        </template>
        <template v-for="key in payKeys" :key="`footer-${key}`" #[`footerRows-cell(${key})`]="{ item }">
            <div class="grid grid-cols-[3.5rem_4.5rem] justify-end gap-2 tabular-nums whitespace-nowrap font-semibold text-gray-700">
                <span class="text-right">{{ item[`${key}_hours`].toFixed(2) }}</span>
                <span class="text-right">{{ money(item[`${key}_amount`]) }}</span>
            </div>
        </template>
        <template v-for="key in tabletKeys" :key="`footer-${key}`" #[`footerRows-cell(${key})`]="{ item }">
            <span class="tabular-nums font-semibold text-gray-700">{{ item[key].toFixed(2) }}</span>
        </template>
        <template #footerRows-cell(total)="{ item }">
            <span class="font-semibold tabular-nums text-gray-800">{{ money(item.total) }}</span>
        </template>
    </Table>

    <Table :resource="data" class="mt-4">
        <template #cell(date)="{ item }">
            <span class="whitespace-nowrap">{{ useFormatTime(item.date) }}</span>
        </template>
        <template #cell(job_order_reference)="{ item }">
            <span class="whitespace-nowrap">{{ item.job_order_reference ?? '—' }}</span>
        </template>
        <template #cell(artefact_code)="{ item }">
            <span class="whitespace-nowrap">{{ item.artefact_code ?? '—' }}</span>
        </template>
        <template #cell(step)="{ item }">
            {{ item.step }}
            <span v-if="item.is_tablet_info" class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-600">{{ ctrans('Hourly: tablet time for information') }}</span>
            <span v-if="item.not_paid_hours" class="ml-1 rounded bg-red-100 px-1.5 py-0.5 text-xs font-medium text-red-800">{{ ctrans(':hours h over the clocked time not paid', { hours: item.not_paid_hours.toFixed(2) }) }}</span>
            <span v-if="item.excluded" class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-800">{{ ctrans('Left running after shift: not counted until checked') }}</span>
        </template>
        <template #cell(hours)="{ item }">
            <span :class="item.excluded || item.is_tablet_info ? 'text-gray-400 line-through' : ''">{{ item.hours.toFixed(2) }}</span>
        </template>
        <template #cell(units)="{ item }">{{ item.units }}</template>
        <template #cell(band_rate)="{ item }">
            {{ money(item.band_rate) }}<span v-if="item.guru_rate" class="text-gray-400"> + {{ money(item.guru_rate) }}</span>
        </template>
        <template #cell(amount)="{ item }">
            <span :class="item.excluded || item.is_tablet_info ? 'text-gray-400 line-through' : ''">{{ money(item.amount) }}</span>
        </template>
        <template #footerRows-cell(hours)="{ item }">
            <span class="font-semibold">{{ item.hours?.toFixed(2) }}</span>
        </template>
        <template #footerRows-cell(units)><span /></template>
        <template #footerRows-cell(band_rate)><span /></template>
        <template #footerRows-cell(amount)="{ item }">
            <span class="font-semibold">{{ item.amount !== undefined ? money(item.amount) : '' }}</span>
        </template>
    </Table>
</template>
