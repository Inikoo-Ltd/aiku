<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 01 Nov 2026 22:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { useLocaleStore } from "@/Stores/locale"
import { edgeDrillHref } from "@/Components/Web/edgeDrill"

defineProps<{
    data: object
    tab?: string
}>()

const locale = useLocaleStore()

const countKeys = [
    "requests", "requests_reached", "requests_varnish", "requests_octane", "avg_origin_ms", "requests_hit", "requests_miss", "requests_expired", "requests_dynamic", "requests_bypass", "requests_bypass_redirect", "requests_none",
    "requests_2xx", "requests_3xx", "requests_4xx", "requests_5xx", "requests_google_bot", "requests_bing_bot", "requests_apple_bot", "requests_other_bots",
    "requests_unknown_agent", "requests_browsers", "requests_far_countries_hit",
]
const shareKeys = ["edge_share", "share_4xx", "unknown_agent_share", "far_share"]

const formatCount = (value: number | null) => (value === null ? "—" : locale.number(value))
const formatShare = (value: number | null) => (value === null ? "—" : `${value.toFixed(1)}%`)

const overviewHref = (slug: string) => {
    const current = new URLSearchParams(window.location.search)
    return edgeDrillHref({ days: current.get("days") }, "showcase", { website: slug })
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(domain)="{ item }">
            <Link :href="overviewHref(item.slug)" class="primaryLink">{{ item.domain }}</Link>
        </template>
        <template #cell(organisation_code)="{ item }">
            <span class="whitespace-nowrap">{{ item.organisation_code }}</span>
        </template>
        <template v-for="key in countKeys" :key="key" #[`cell(${key})`]="{ item }">
            <span class="tabular-nums">{{ formatCount(item[key]) }}</span>
        </template>
        <template v-for="key in shareKeys" :key="key" #[`cell(${key})`]="{ item }">
            <span class="tabular-nums">{{ formatShare(item[key]) }}</span>
        </template>
    </Table>
</template>
