<!--
  - Author: Vika Aqordi <aqordeon@gmail.com>
  - Created: Tue, 16 Sep 2026, Bali, Indonesia
  - Copyright (c) 2026, Inikoo LTD
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faBarcode, faCalendarAlt, faHashtag } from "@fal"
import { trans } from "laravel-vue-i18n"
import Table from "@/Components/Table/Table.vue"
import { useFormatTime } from "@/Composables/useFormatTime"

library.add(faBarcode, faCalendarAlt, faHashtag)

defineProps<{
    data: object
    tab?: string
}>()

const SOURCE_BADGES: Record<string, { icon: string, label: string }> = {
    batch_code: { icon: "fal fa-hashtag", label: trans("Batch code") },
    expiry_date: { icon: "fal fa-calendar-alt", label: trans("Expiry date") },
    barcode: { icon: "fal fa-barcode", label: trans("Barcode") },
}

const artefactRoute = (label: { artefact_slug: string }) =>
    route("grp.org.productions.show.crafts.artefacts.show", [
        route().params["organisation"],
        route().params["production"],
        label.artefact_slug,
    ])

const labelRoute = (label: { artefact_slug: string }) =>
    route("grp.org.productions.show.crafts.artefacts.show", {
        organisation: route().params["organisation"],
        production: route().params["production"],
        artefact: label.artefact_slug,
        tab: "labels",
    })
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(artefact_code)="{ item: label }">
            <Link :href="artefactRoute(label)" class="primaryLink">{{ label.artefact_code }}</Link>
        </template>

        <template #cell(name)="{ item: label }">
            <Link :href="labelRoute(label)" class="primaryLink">{{ label.name }}</Link>
        </template>

        <template #cell(sources)="{ item: label }">
            <div v-if="label.sources.length" class="flex items-center gap-1.5">
                <span
                    v-for="source in label.sources"
                    :key="source"
                    class="flex h-5 w-5 items-center justify-center rounded bg-indigo-50 text-[10px] text-indigo-600"
                    :title="SOURCE_BADGES[source]?.label ?? source"
                    :aria-label="SOURCE_BADGES[source]?.label ?? source">
                    <FontAwesomeIcon :icon="SOURCE_BADGES[source]?.icon ?? 'fal fa-hashtag'" fixed-width aria-hidden="true" />
                </span>
            </div>
            <span v-else class="text-gray-400">-</span>
        </template>

        <template #cell(published_at)="{ item: label }">
            <span v-if="label.published_at">{{ useFormatTime(label.published_at, { formatTime: 'aiku' }) }}</span>
            <span v-else class="text-gray-400">-</span>
        </template>
    </Table>
</template>
