<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 01 Nov 2026 22:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { useLocaleStore } from "@/Stores/locale"
import { ctrans } from "@/Composables/useTrans"
import { edgeDrillHref } from "@/Components/Web/edgeDrill"

defineProps<{
    data: object
    tab?: string
}>()

const locale = useLocaleStore()
const regionNames = new Intl.DisplayNames(undefined, { type: "region" })

const countryName = (code: string | null) => {
    if (!code) {
        return ctrans("Unknown")
    }
    try {
        return regionNames.of(code) ?? code
    } catch {
        return code
    }
}

const current = () => new URLSearchParams(window.location.search)
const countryHref = (code: string | null) => edgeDrillHref({ website: current().get("website"), days: current().get("days") }, "visitor_countries", { country: code, day: current().get("day") })
const overviewHref = (slug: string) => edgeDrillHref({ days: current().get("days") }, "showcase", { website: slug })
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(country_code)="{ item }">
            <div class="flex items-center gap-2 whitespace-nowrap">
                <img v-if="item.country_code" :src="`/flags/${item.country_code.toLowerCase()}.png`" :alt="item.country_code" class="h-3 w-4 rounded-sm object-cover" />
                <span v-else class="h-3 w-4 rounded-sm bg-gray-200" />
                <Link v-if="item.country_code" :href="countryHref(item.country_code)" class="primaryLink">{{ countryName(item.country_code) }}</Link>
                <span v-else>{{ countryName(null) }}</span>
                <span v-if="item.is_far" v-tooltip.top="ctrans('Far country: almost all scrapers')" class="rounded-sm bg-amber-50 px-1 text-xs text-amber-700">{{ ctrans("far") }}</span>
            </div>
        </template>
        <template #cell(domain)="{ item }">
            <Link :href="overviewHref(item.website_slug)" class="primaryLink">{{ item.domain }}</Link>
        </template>
        <template #cell(sessions)="{ item }">
            <Link v-if="item.visitors_url" :href="item.visitors_url" class="primaryLink tabular-nums">{{ locale.number(item.sessions) }}</Link>
            <span v-else class="tabular-nums">{{ locale.number(item.sessions) }}</span>
        </template>
        <template #cell(logged_in_sessions)="{ item }">
            <span class="tabular-nums">{{ locale.number(item.logged_in_sessions) }}</span>
        </template>
        <template #cell(logged_in_share)="{ item }">
            <span class="tabular-nums">{{ item.logged_in_share === null ? "—" : `${item.logged_in_share.toFixed(1)}%` }}</span>
        </template>
        <template #cell(page_views)="{ item }">
            <span class="tabular-nums">{{ locale.number(item.page_views) }}</span>
        </template>
    </Table>
</template>
