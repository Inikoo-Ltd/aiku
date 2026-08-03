<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 15 Jul 2026 05:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3"
import { ref } from "vue"
import { capitalize } from "@/Composables/capitalize"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import SearchAnalyticsDisplay from "@/Components/DataDisplay/Dashboard/Widget/SearchAnalyticsDisplay.vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faExternalLink, faTimes, faSearch, faUsers } from "@fal"

library.add(faExternalLink, faTimes, faSearch, faUsers)

defineProps<{
    pageHead: any
    title: string
    insights: any
    data: any
    users: any
}>()

const activeTab = ref<'searches' | 'users'>('searches')

const tabs = [
    { key: 'searches', label: 'Searches', icon: 'fal fa-search' },
    { key: 'users', label: 'By user', icon: 'fal fa-users' },
] as const
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="p-4">
        <SearchAnalyticsDisplay :widget="insights" />
    </div>

    <div class="px-4 flex gap-2">
        <button
            v-for="tab in tabs"
            :key="tab.key"
            type="button"
            class="px-4 py-2 rounded-md text-sm font-medium transition"
            :class="activeTab === tab.key ? 'bg-slate-700 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
            @click="activeTab = tab.key"
        >
            <FontAwesomeIcon :icon="tab.icon" fixed-width aria-hidden="true" />
            {{ ctrans(tab.label) }}
        </button>
    </div>

    <Table v-show="activeTab === 'searches'" :resource="data" class="mt-2">
        <template #cell(created_at)="{ item }">
            <span class="whitespace-nowrap text-gray-500">{{ useFormatTime(item.created_at, { formatTime: 'hms', keepTimezone: true }) }}</span>
        </template>

        <template #cell(query)="{ item }">
            <span class="font-medium">{{ item.query }}</span>
        </template>

        <template #cell(scope)="{ item }">
            <span class="text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 capitalize">{{ item.scope.replaceAll('_', ' ') }}</span>
        </template>

        <template #cell(context)="{ item }">
            <span class="text-gray-500 text-xs">
                {{ [item.organisation_code, item.shop_code].filter(Boolean).join(' / ') || '-' }}
            </span>
        </template>

        <template #cell(results_count)="{ item }">
            <span :class="item.results_count === 0 ? 'text-red-500 font-medium' : 'tabular-nums'">{{ item.results_count }}</span>
        </template>

        <template #cell(clicked_at)="{ item }">
            <a
                v-if="item.clicked_at"
                :href="item.clicked_url"
                class="text-green-600 hover:underline whitespace-nowrap text-xs"
                v-tooltip="item.clicked_url"
            >
                <FontAwesomeIcon icon="fal fa-external-link" fixed-width aria-hidden="true" />
                {{ useFormatTime(item.clicked_at, { formatTime: 'hms', keepTimezone: true }) }}
            </a>
            <FontAwesomeIcon v-else icon="fal fa-times" class="text-gray-300" fixed-width aria-hidden="true" />
        </template>
    </Table>

    <Table v-show="activeTab === 'users'" :resource="users" name="users" class="mt-2">
        <template #cell(username)="{ item }">
            <Link
                :href="`${route('grp.sysadmin.search_logs.index')}?filter[global]=${encodeURIComponent(item.username)}`"
                class="font-medium text-indigo-600 hover:underline"
                v-tooltip="ctrans('See searches by this user')"
            >
                {{ item.username }}
            </Link>
        </template>

        <template #cell(searches)="{ item }">
            <span class="tabular-nums font-medium">{{ item.searches.toLocaleString() }}</span>
        </template>

        <template #cell(clicks)="{ item }">
            <span class="tabular-nums">{{ item.clicks.toLocaleString() }}</span>
        </template>

        <template #cell(click_through)="{ item }">
            <span class="tabular-nums">{{ item.click_through }}%</span>
        </template>

        <template #cell(zero_results)="{ item }">
            <span :class="item.zero_results > 0 ? 'text-red-500' : 'text-gray-400'" class="tabular-nums">{{ item.zero_results }}</span>
        </template>

        <template #cell(last_searched_at)="{ item }">
            <span class="whitespace-nowrap text-gray-500">{{ useFormatTime(item.last_searched_at, { formatTime: 'hms', keepTimezone: true }) }}</span>
        </template>
    </Table>
</template>
