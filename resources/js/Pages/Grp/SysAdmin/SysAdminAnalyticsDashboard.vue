<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 05 Jan 2025 14:59:28 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import MiniBar from '@/Components/DataDisplay/Dashboard/Widget/MiniBar.vue'
import Chart from 'primevue/chart'
import { computed } from 'vue'

import { capitalize } from "@/Composables/capitalize"
import { useFormatTime } from '@/Composables/useFormatTime'

import { PageHeadingTypes } from '@/types/PageHeading'

type DayCount = { date: string, count: number }
type UserCount = { username: string, slug: string, requests: number }
type NameCount = { name: string, count: number }
type OnlineUser = { username: string, slug: string, last_route_name: string, last_seen_at: string, requests_today: number }

const props = defineProps<{
  title: string,
  pageHead: PageHeadingTypes
  analytics: {
    requests_today: number
    online_now: OnlineUser[]
    online_count: number
    active_users_30d: number
    logins_30d: number
    requests_per_day: DayCount[]
    logins_per_day: DayCount[]
    top_users_30d: UserCount[]
    top_modules_30d: NameCount[]
    devices_30d: NameCount[]
    browsers_30d: NameCount[]
  } | null
}>()

const maxOf = (list: { requests?: number, count?: number }[] | undefined, key: 'requests' | 'count') =>
    Math.max(0, ...((list ?? []).map(item => item[key] ?? 0)))

const maxUsers = computed(() => maxOf(props.analytics?.top_users_30d, 'requests'))
const maxOnline = computed(() => maxOf(props.analytics?.online_now, 'requests_today'))
const maxModules = computed(() => maxOf(props.analytics?.top_modules_30d, 'count'))
const maxDevices = computed(() => maxOf(props.analytics?.devices_30d, 'count'))
const maxBrowsers = computed(() => maxOf(props.analytics?.browsers_30d, 'count'))

const compactNumber = new Intl.NumberFormat(undefined, { notation: 'compact', maximumFractionDigits: 1 })
const shortDate = new Intl.DateTimeFormat(undefined, { day: 'numeric', month: 'short' })
const fullDate = new Intl.DateTimeFormat(undefined, { weekday: 'short', day: 'numeric', month: 'long' })

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: {
        legend: { display: false },
        tooltip: {
            backgroundColor: '#1f2937',
            padding: 10,
            cornerRadius: 4,
            displayColors: false,
            titleFont: { size: 11, weight: '400' },
            bodyFont: { size: 13, weight: '600' },
            callbacks: {
                title: (items: { label: string }[]) => fullDate.format(new Date(items[0].label)),
                label: (item: { raw: number }) => item.raw.toLocaleString(),
            },
        },
    },
    scales: {
        y: {
            beginAtZero: true,
            border: { display: false },
            grid: { color: '#e5e7eb', drawTicks: false, borderDash: [3, 3] },
            ticks: {
                maxTicksLimit: 4,
                padding: 8,
                color: '#9ca3af',
                font: { size: 11 },
                callback: (value: number) => compactNumber.format(value).toUpperCase(),
            },
        },
        x: {
            border: { display: false },
            grid: { display: false },
            ticks: {
                autoSkip: true,
                maxTicksLimit: 6,
                maxRotation: 0,
                padding: 6,
                color: '#9ca3af',
                font: { size: 11 },
                callback: function (this: { getLabelForValue: (v: number) => string }, value: number) {
                    return shortDate.format(new Date(this.getLabelForValue(value)))
                },
            },
        },
    },
}

const lineChartData = (series: DayCount[] | undefined, label: string, color: string, fill: string) => ({
    labels: (series ?? []).map(d => d.date),
    datasets: [
        {
            label,
            data: (series ?? []).map(d => d.count),
            borderColor: color,
            backgroundColor: fill,
            borderWidth: 1.5,
            fill: true,
            tension: 0.35,
            pointRadius: 0,
            pointHoverRadius: 4,
            pointHoverBackgroundColor: color,
            pointHoverBorderColor: '#ffffff',
            pointHoverBorderWidth: 2,
        },
    ],
})

const requestsChartData = computed(() => lineChartData(props.analytics?.requests_per_day, 'Requests', '#6c5fc7', 'rgba(108,95,199,0.09)'))
const loginsChartData = computed(() => lineChartData(props.analytics?.logins_per_day, 'Logins', '#a78bfa', 'rgba(167,139,250,0.09)'))

const minutesAgo = (dateIso: string) => Math.max(0, Math.round((Date.now() - new Date(dateIso).getTime()) / 60000))
</script>

<template>
  <Head :title="capitalize(title)" />
  <PageHeading :data="pageHead"></PageHeading>

  <div class="px-4 py-4 space-y-4">
    <div class="bg-white rounded-lg p-4 shadow-sm border border-gray-300">
      <div class="flex flex-wrap gap-x-10 gap-y-4">
        <div>
          <p class="text-4xl font-bold text-indigo-600">{{ (analytics?.requests_today ?? 0).toLocaleString() }}</p>
          <p class="text-sm text-gray-600">{{ ctrans('Requests today') }}</p>
        </div>
        <div>
          <p class="text-4xl font-bold text-emerald-600 flex items-center gap-2">
            <span class="relative flex h-2.5 w-2.5">
              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75" />
              <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500" />
            </span>
            {{ (analytics?.online_count ?? 0).toLocaleString() }}
          </p>
          <p class="text-sm text-gray-600">{{ ctrans('Online now') }}</p>
        </div>
        <div>
          <p class="text-4xl font-bold text-sky-600">{{ (analytics?.active_users_30d ?? 0).toLocaleString() }}</p>
          <p class="text-sm text-gray-600">{{ ctrans('Active users') }}</p>
        </div>
        <div>
          <p class="text-4xl font-bold text-violet-600">{{ (analytics?.logins_30d ?? 0).toLocaleString() }}</p>
          <p class="text-sm text-gray-600">{{ ctrans('Logins') }}</p>
        </div>
      </div>
    </div>

    <div class="grid lg:grid-cols-2 gap-4">
      <div class="bg-white rounded-lg p-4 shadow-sm border border-gray-300">
        <p class="text-xs text-gray-400 font-medium mb-2"><span class="inline-block w-2 h-2 rounded-full bg-indigo-400 mr-1" />{{ ctrans('Requests per day') }}</p>
        <div class="h-64">
          <Chart type="line" :data="requestsChartData" :options="chartOptions" class="h-full" />
        </div>
      </div>
      <div class="bg-white rounded-lg p-4 shadow-sm border border-gray-300">
        <p class="text-xs text-gray-400 font-medium mb-2"><span class="inline-block w-2 h-2 rounded-full bg-violet-400 mr-1" />{{ ctrans('Logins per day') }}</p>
        <div class="h-64">
          <Chart type="line" :data="loginsChartData" :options="chartOptions" class="h-full" />
        </div>
      </div>
    </div>

    <div class="grid lg:grid-cols-3 gap-4 text-sm">
      <div class="bg-white rounded-lg p-4 shadow-sm border border-gray-300">
        <p class="text-xs text-gray-400 font-medium mb-1"><span class="inline-block w-2 h-2 rounded-full bg-emerald-400 mr-1" />{{ ctrans('Online now') }}</p>
        <div class="divide-y divide-gray-100">
          <Link
              v-for="user in analytics?.online_now"
              :key="user.username"
              :href="route('grp.sysadmin.users.show', [user.slug])"
              class="block py-1 hover:bg-slate-50"
              v-tooltip="user.last_route_name"
          >
            <div class="flex justify-between gap-2">
              <span class="text-gray-600 truncate min-w-0">{{ user.username }}</span>
              <span class="shrink-0 tabular-nums font-medium">{{ minutesAgo(user.last_seen_at) }} {{ ctrans('min ago') }}</span>
            </div>
            <MiniBar :value="user.requests_today" :max="maxOnline" color="bg-emerald-400" />
          </Link>
          <p v-if="!analytics?.online_now.length" class="py-1 text-gray-400">{{ ctrans('No data yet') }}</p>
        </div>
      </div>

      <div class="bg-white rounded-lg p-4 shadow-sm border border-gray-300">
        <p class="text-xs text-gray-400 font-medium mb-1"><span class="inline-block w-2 h-2 rounded-full bg-indigo-400 mr-1" />{{ ctrans('Most active users') }}</p>
        <div class="divide-y divide-gray-100">
          <Link
              v-for="user in analytics?.top_users_30d"
              :key="user.username"
              :href="route('grp.sysadmin.users.show', [user.slug])"
              class="block py-1 hover:bg-slate-50"
          >
            <div class="flex justify-between gap-2">
              <span class="text-gray-600 truncate min-w-0">{{ user.username }}</span>
              <span class="shrink-0 tabular-nums font-medium">{{ user.requests.toLocaleString() }}</span>
            </div>
            <MiniBar :value="user.requests" :max="maxUsers" color="bg-indigo-400" />
          </Link>
          <p v-if="!analytics?.top_users_30d.length" class="py-1 text-gray-400">{{ ctrans('No data yet') }}</p>
        </div>
      </div>

      <div class="bg-white rounded-lg p-4 shadow-sm border border-gray-300">
        <p class="text-xs text-gray-400 font-medium mb-1"><span class="inline-block w-2 h-2 rounded-full bg-violet-400 mr-1" />{{ ctrans('Top modules') }}</p>
        <div class="divide-y divide-gray-100">
          <div v-for="module in analytics?.top_modules_30d" :key="module.name" class="py-1">
            <div class="flex justify-between gap-2">
              <span class="text-gray-600 truncate min-w-0 capitalize">{{ module.name }}</span>
              <span class="shrink-0 tabular-nums font-medium">{{ module.count.toLocaleString() }}</span>
            </div>
            <MiniBar :value="module.count" :max="maxModules" color="bg-violet-400" />
          </div>
          <p v-if="!analytics?.top_modules_30d.length" class="py-1 text-gray-400">{{ ctrans('No data yet') }}</p>
        </div>
      </div>

      <div class="bg-white rounded-lg p-4 shadow-sm border border-gray-300">
        <p class="text-xs text-gray-400 font-medium mb-1"><span class="inline-block w-2 h-2 rounded-full bg-sky-400 mr-1" />{{ ctrans('Devices') }}</p>
        <div class="divide-y divide-gray-100">
          <div v-for="device in analytics?.devices_30d" :key="device.name" class="py-1">
            <div class="flex justify-between gap-2">
              <span class="text-gray-600 truncate min-w-0 capitalize">{{ device.name }}</span>
              <span class="shrink-0 tabular-nums font-medium">{{ device.count.toLocaleString() }}</span>
            </div>
            <MiniBar :value="device.count" :max="maxDevices" color="bg-sky-400" />
          </div>
          <p v-if="!analytics?.devices_30d.length" class="py-1 text-gray-400">{{ ctrans('No data yet') }}</p>
        </div>
      </div>

      <div class="bg-white rounded-lg p-4 shadow-sm border border-gray-300">
        <p class="text-xs text-gray-400 font-medium mb-1"><span class="inline-block w-2 h-2 rounded-full bg-violet-400 mr-1" />{{ ctrans('Browsers') }}</p>
        <div class="divide-y divide-gray-100">
          <div v-for="browser in analytics?.browsers_30d" :key="browser.name" class="py-1">
            <div class="flex justify-between gap-2">
              <span class="text-gray-600 truncate min-w-0 capitalize">{{ browser.name }}</span>
              <span class="shrink-0 tabular-nums font-medium">{{ browser.count.toLocaleString() }}</span>
            </div>
            <MiniBar :value="browser.count" :max="maxBrowsers" color="bg-violet-400" />
          </div>
          <p v-if="!analytics?.browsers_30d.length" class="py-1 text-gray-400">{{ ctrans('No data yet') }}</p>
        </div>
      </div>
    </div>
  </div>
</template>
