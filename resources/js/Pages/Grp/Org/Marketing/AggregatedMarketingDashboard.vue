<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 07 Aug 2026
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from '@/Composables/capitalize'
import { trans } from 'laravel-vue-i18n'
import { useLocaleStore } from '@/Stores/locale'
import { route } from 'ziggy-js'
import { PageHeadingTypes } from '@/types/PageHeading'

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    overview: {
        scope: 'group' | 'organisation'
        children_label: string
        currency_code: string
        period: string
        period_label: string
        period_options: { value: string, label: string }[]
        totals: {
            spend: number
            revenue: number
            registrations: number
            orders: number
            roas: number | null
            cac: number | null
        }
        channels: {
            name: string
            type: string
            spend: number
            revenue: number
            registrations: number
            orders: number
            roas: number | null
        }[]
        children: {
            name: string
            slug: string
            revenue: number
            registrations: number
            orders: number
            route: { name: string, parameters: string[] }
        }[]
    }
}>()

const locale = useLocaleStore()

const money = (value: number) => locale.currencyFormat(props.overview.currency_code, value)
const count = (value: number) => Number.isInteger(value) ? value.toString() : value.toFixed(2)

const changePeriod = (event: Event) => {
    router.get(
        window.location.pathname,
        { period: (event.target as HTMLSelectElement).value },
        { preserveState: true, preserveScroll: true }
    )
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="px-4 py-4 space-y-4">
        <div class="flex items-center justify-between">
            <span class="text-xs text-gray-500">
                {{ trans('All figures in') }} {{ overview.currency_code }}
            </span>
            <select :value="overview.period" @change="changePeriod"
                    class="text-xs border-gray-200 rounded-md py-1 pl-2 pr-7">
                <option v-for="option in overview.period_options" :key="option.value" :value="option.value">
                    {{ option.label }}
                </option>
            </select>
        </div>

        <!-- Headline: the four numbers management asks for -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div class="rounded-xl ring-1 ring-gray-200 bg-white p-4">
                <div class="text-xs text-gray-400">{{ trans('Attributed revenue') }}</div>
                <div class="mt-1 text-lg tabular-nums">{{ money(overview.totals.revenue) }}</div>
            </div>
            <div class="rounded-xl ring-1 ring-gray-200 bg-white p-4">
                <div class="text-xs text-gray-400">{{ trans('Registrations') }}</div>
                <div class="mt-1 text-lg tabular-nums">{{ count(overview.totals.registrations) }}</div>
            </div>
            <div class="rounded-xl ring-1 ring-gray-200 bg-white p-4">
                <div class="text-xs text-gray-400">{{ trans('Orders') }}</div>
                <div class="mt-1 text-lg tabular-nums">{{ count(overview.totals.orders) }}</div>
            </div>
            <div class="rounded-xl ring-1 ring-gray-200 bg-white p-4">
                <div class="text-xs text-gray-400">{{ trans('Ad spend') }}</div>
                <div class="mt-1 text-lg tabular-nums">{{ money(overview.totals.spend) }}</div>
                <div class="mt-0.5 text-xs" :class="overview.totals.roas === null ? 'text-gray-300' : overview.totals.roas >= 1 ? 'text-[#006300]' : 'text-[#d03b3b]'">
                    {{ trans('ROAS') }} {{ overview.totals.roas !== null ? overview.totals.roas.toFixed(2) + '×' : '—' }}
                </div>
            </div>
        </div>

        <!-- Channels: the whole point of the aggregate, which channel earns across every shop -->
        <div v-if="overview.channels.length" class="rounded-xl ring-1 ring-gray-200 bg-white p-5">
            <span class="text-sm font-medium text-gray-800">{{ trans('Channels') }}</span>
            <span class="ml-2 text-xs text-gray-400">{{ overview.period_label.toLowerCase() }}</span>

            <table class="mt-4 w-full text-xs">
                <thead>
                    <tr class="text-gray-400 border-b border-gray-100">
                        <th class="text-left font-normal py-1.5 pr-2">{{ trans('Channel') }}</th>
                        <th class="text-right font-normal py-1.5 px-2">{{ trans('Spend') }}</th>
                        <th class="text-right font-normal py-1.5 px-2">{{ trans('Revenue') }}</th>
                        <th class="text-right font-normal py-1.5 px-2">{{ trans('Registrations') }}</th>
                        <th class="text-right font-normal py-1.5 px-2">{{ trans('Orders') }}</th>
                        <th class="text-right font-normal py-1.5 pl-2">{{ trans('ROAS') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="channel in overview.channels" :key="channel.type"
                        class="border-b border-gray-50 text-gray-600">
                        <td class="py-2 pr-2 text-gray-700">{{ channel.name }}</td>
                        <td class="text-right px-2 tabular-nums">{{ money(channel.spend) }}</td>
                        <td class="text-right px-2 tabular-nums">{{ money(channel.revenue) }}</td>
                        <td class="text-right px-2 tabular-nums">{{ count(channel.registrations) }}</td>
                        <td class="text-right px-2 tabular-nums">{{ count(channel.orders) }}</td>
                        <td class="text-right pl-2 tabular-nums"
                            :class="channel.roas === null ? 'text-gray-300' : channel.roas >= 1 ? 'text-[#006300]' : 'text-[#d03b3b]'">
                            {{ channel.roas !== null ? channel.roas.toFixed(2) + '×' : '—' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- The drill-down: link down a level rather than repeat that level's dashboard here -->
        <div v-if="overview.children.length" class="rounded-xl ring-1 ring-gray-200 bg-white p-5">
            <span class="text-sm font-medium text-gray-800">{{ overview.children_label }}</span>

            <table class="mt-4 w-full text-xs">
                <thead>
                    <tr class="text-gray-400 border-b border-gray-100">
                        <th class="text-left font-normal py-1.5 pr-2">{{ trans('Name') }}</th>
                        <th class="text-right font-normal py-1.5 px-2">{{ trans('Revenue') }}</th>
                        <th class="text-right font-normal py-1.5 px-2">{{ trans('Registrations') }}</th>
                        <th class="text-right font-normal py-1.5 pl-2">{{ trans('Orders') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="child in overview.children" :key="child.slug"
                        class="border-b border-gray-50 text-gray-600">
                        <td class="py-2 pr-2">
                            <Link :href="route(child.route.name, child.route.parameters)"
                                  class="text-gray-700 hover:text-indigo-600">
                                {{ child.name }}
                            </Link>
                        </td>
                        <td class="text-right px-2 tabular-nums">{{ money(child.revenue) }}</td>
                        <td class="text-right px-2 tabular-nums">{{ count(child.registrations) }}</td>
                        <td class="text-right pl-2 tabular-nums">{{ count(child.orders) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="!overview.channels.length" class="text-xs text-gray-400">
            {{ trans('No attributed marketing activity in this period yet.') }}
        </div>
    </div>
</template>
