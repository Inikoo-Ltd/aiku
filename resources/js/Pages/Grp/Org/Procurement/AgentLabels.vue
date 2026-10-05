<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Thu, 24 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  -  Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import { computed, ref } from "vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPrint, faTags } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { PageHeadingTypes } from "@/types/PageHeading"

library.add(faPrint, faTags)

interface AgentLabel {
    id: number
    name: string
    run_sources: string[]
    pdf_url: string
}

interface AgentOrgStock {
    id: number
    code: string
    name: string
    organisation: string
    labels: AgentLabel[]
}

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    data: {
        org_stocks: AgentOrgStock[]
        number_without_labels: number
    }
}>()

const search = ref("")
const printingLabelId = ref<number | null>(null)
const batchCode = ref("")
const expiryDate = ref("")

const orgStocks = computed(() => {
    const query = search.value.trim().toLowerCase()

    return query
        ? props.data.org_stocks.filter(orgStock => `${orgStock.code} ${orgStock.name}`.toLowerCase().includes(query))
        : props.data.org_stocks
})

const openPrint = (label: AgentLabel) => {
    printingLabelId.value = printingLabelId.value === label.id ? null : label.id
    batchCode.value = ""
    expiryDate.value = ""
}

const isReadyToPrint = (label: AgentLabel) =>
    (!label.run_sources.includes("batch_code") || batchCode.value.trim() !== "")
    && (!label.run_sources.includes("expiry_date") || expiryDate.value !== "")

const print = (label: AgentLabel) => {
    if (!isReadyToPrint(label)) return

    const url = new URL(label.pdf_url)

    if (batchCode.value.trim()) url.searchParams.set("batch_code", batchCode.value.trim())
    if (expiryDate.value) url.searchParams.set("expiry_date", expiryDate.value)

    window.open(url.toString(), "_blank")
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="p-4 space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <input
                v-model="search"
                type="search"
                class="w-72 rounded border border-gray-300 px-2 py-1 text-sm"
                :placeholder="ctrans('Search SKO code or name')"
                :aria-label="ctrans('Search SKO code or name')" />
            <div v-if="data.number_without_labels" class="text-xs text-gray-500" role="status">
                {{ ctrans(':count of the products you buy for us have no published label yet.', { count: String(data.number_without_labels) }) }}
            </div>
        </div>

        <div
            v-if="!orgStocks.length"
            class="rounded border border-dashed border-gray-300 px-3 py-4 text-center text-sm text-gray-500"
            role="status">
            {{ ctrans('No published labels to print yet.') }}
        </div>

        <ul v-else class="divide-y divide-gray-200 rounded-lg border border-gray-200 bg-white">
            <li v-for="orgStock in orgStocks" :key="orgStock.id" class="px-4 py-3">
                <div class="flex flex-wrap items-baseline gap-x-3">
                    <span class="text-sm font-semibold">{{ orgStock.code }}</span>
                    <span class="text-sm">{{ orgStock.name }}</span>
                    <span class="text-xs text-gray-500">{{ orgStock.organisation }}</span>
                </div>

                <ul class="mt-2 space-y-2">
                    <li v-for="label in orgStock.labels" :key="label.id">
                        <div class="flex items-center gap-2">
                            <FontAwesomeIcon icon="fal fa-tags" class="text-gray-400" fixed-width aria-hidden="true" />
                            <span class="text-sm">{{ label.name }}</span>
                            <Button
                                type="tertiary"
                                size="xs"
                                icon="fal fa-print"
                                :label="ctrans('Print')"
                                :aria-expanded="printingLabelId === label.id"
                                @click="openPrint(label)" />
                        </div>

                        <form
                            v-if="printingLabelId === label.id"
                            class="mt-2 ml-6 flex flex-wrap items-end gap-3 rounded bg-gray-50 p-3"
                            @submit.prevent>
                            <label v-if="label.run_sources.includes('batch_code')" class="text-xs text-gray-600">
                                {{ ctrans('Batch code') }}
                                <input v-model="batchCode" type="text" required class="mt-1 block w-48 rounded border border-gray-300 px-2 py-1 text-sm" />
                            </label>
                            <label v-if="label.run_sources.includes('expiry_date')" class="text-xs text-gray-600">
                                {{ ctrans('Expiry date') }}
                                <input v-model="expiryDate" type="date" required class="mt-1 block rounded border border-gray-300 px-2 py-1 text-sm" />
                            </label>
                            <Button type="primary" size="xs" icon="fal fa-print" :label="ctrans('Open PDF')" :disabled="!isReadyToPrint(label)" @click="print(label)" />
                        </form>
                    </li>
                </ul>
            </li>
        </ul>
    </div>
</template>
