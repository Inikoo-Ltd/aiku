<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 28 Aug 2026 20:30:00 British Summer Time, Sheffield, UK
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { ref } from "vue"
import { trans } from "laravel-vue-i18n"
import { faChartLine } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import Table from "@/Components/Table/Table.vue"
import TableBetweenFilter from "@/Components/Table/TableBetweenFilter.vue"
import { PageHeadingTypes } from "@/types/PageHeading"

library.add(faChartLine)

defineProps<{
    title: string
    pageHead: PageHeadingTypes
    pickers?: {}
    packers?: {}
}>()

const channels = [
    { value: "all", label: trans("All") },
    { value: "wholesale", label: trans("Wholesale") },
    { value: "b2c", label: trans("B2C") },
    { value: "dropshipping", label: trans("Dropshipping") },
    { value: "fulfilment", label: trans("Fulfilment") },
]
const currentChannel = ref(new URLSearchParams(window.location.search).get("channel") ?? "all")
const setChannel = (value: string) => {
    currentChannel.value = value
    router.reload({ data: { channel: value === "all" ? undefined : value } })
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>

    <div class="px-4 pt-4 space-y-8">
        <div class="flex flex-wrap items-center justify-end gap-3">
            <div class="flex rounded-md border border-gray-200 overflow-hidden text-xs">
                <button
                    v-for="channel in channels"
                    :key="channel.value"
                    type="button"
                    class="px-2.5 py-1.5 transition-colors"
                    :class="currentChannel === channel.value ? 'bg-indigo-600 text-white' : 'bg-white hover:bg-gray-50'"
                    @click="setChannel(channel.value)"
                >
                    {{ channel.label }}
                </button>
            </div>
            <TableBetweenFilter :optionsList="['date']" tableName="reports" />
        </div>

        <div>
            <h2 class="font-semibold text-lg">{{ trans("Pickers") }}</h2>
            <Table :resource="pickers" name="pickers" />
        </div>

        <div>
            <h2 class="font-semibold text-lg">{{ trans("Packers") }}</h2>
            <Table :resource="packers" name="packers" />
        </div>
    </div>
</template>
