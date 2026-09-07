<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Wed, 02 Sep 2026, Kuala Lumpur, Malaysia
  -  Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, nextTick, ref, watch } from "vue"
import { Head, Link } from "@inertiajs/vue3"
import axios from "axios"
import { debounce } from "lodash-es"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBarcodeRead, faCheck, faCheckCircle, faExchange, faSearch, faTimesCircle, faTimes, faInventory, faBox, faArrowLeft } from "@fal"
import { trans } from "laravel-vue-i18n"
import { capitalize } from "@/Composables/capitalize"
import { playNotificationSound } from "@/Composables/useNotificationSound"
import { useBarcodeScanner } from "@/Composables/useBarcodeScanner"
import { notify } from "@kyvg/vue3-notification"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Image from "@/Common/Components/Image.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from "@/types/route"

library.add(faBarcodeRead, faCheck, faCheckCircle, faExchange, faSearch, faTimesCircle, faTimes, faInventory, faBox, faArrowLeft)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    can_edit: boolean
    scan_route: routeType
    search_route: routeType
    assign_route: routeType
}>()

type OrgStockCard = {
    id: number
    slug: string
    code: string
    name: string
    state: { icon: string; class: string; tooltip: string }
    barcode: string | null
    unit_barcode: string | null
    packed_in: string | number | null
    quantity_in_locations: string | number
    image: any
    locations: { code: string; quantity: string | number }[]
    route: routeType
}

type ScanResult = { barcode: string; found: boolean; matched_on: "sko" | "unit" | null; org_stock: OrgStockCard | null }
type SearchRow = { id: number; slug: string; code: string; name: string; quantity_in_locations: string | number }

const isScanning = ref(false)
const result = ref<ScanResult | null>(null)
const justAssigned = ref(false)
const okCount = ref(0)

const mode = ref<"scan" | "search">("scan")
const searchQuery = ref("")
const searchRows = ref<SearchRow[]>([])
const isSearching = ref(false)
const searchInput = ref<HTMLInputElement | null>(null)
const candidate = ref<SearchRow | null>(null)
const isAssigning = ref(false)

const scannedBarcode = computed(() => result.value?.barcode ?? "")

const scan = async (code: string) => {
    const barcode = code.trim()
    if (!barcode || isScanning.value) {
        return
    }

    if (candidate.value) {
        result.value = { barcode, found: false, matched_on: null, org_stock: null }
        playNotificationSound({ frequency: 880, duration: 60 })
        return
    }

    isScanning.value = true
    justAssigned.value = false
    mode.value = "scan"

    try {
        const { data } = await axios.get<ScanResult>(route(props.scan_route.name, props.scan_route.parameters), { params: { barcode } })
        result.value = data
        if (data.found) {
            playNotificationSound({ frequency: 1180, duration: 90 })
        } else {
            playNotificationSound({ frequency: 200, duration: 280, type: "square" })
        }
    } catch (error: any) {
        notify({ title: trans("Scan failed"), text: error?.response?.data?.message ?? trans("Please try again."), type: "error" })
    } finally {
        isScanning.value = false
    }
}

const { buffer, inputElement, registerKeystroke, clearBuffer, flushBuffer } = useBarcodeScanner(scan)

const confirmOk = () => {
    okCount.value++
    playNotificationSound({ frequency: 880, duration: 60 })
    result.value = null
    justAssigned.value = false
}

const openSearch = async () => {
    mode.value = "search"
    searchQuery.value = ""
    searchRows.value = []
    candidate.value = null
    await nextTick()
    searchInput.value?.focus()
}

const closeSearch = () => {
    mode.value = "scan"
    candidate.value = null
}

const runSearch = debounce(async (query: string) => {
    if (query.trim().length < 2) {
        searchRows.value = []
        return
    }

    isSearching.value = true
    try {
        const { data } = await axios.get(route(props.search_route.name, props.search_route.parameters), {
            params: { "filter[global]": query.trim(), perPage: 30 },
        })
        searchRows.value = data.data
    } finally {
        isSearching.value = false
    }
}, 250)

watch(searchQuery, query => runSearch(query))

const assign = async () => {
    if (!candidate.value || !scannedBarcode.value || isAssigning.value) {
        return
    }

    isAssigning.value = true
    try {
        const { data } = await axios.patch<ScanResult>(
            route(props.assign_route.name, { ...props.assign_route.parameters, orgStock: candidate.value.slug }),
            { barcode: scannedBarcode.value }
        )
        result.value = data
        justAssigned.value = true
        mode.value = "scan"
        candidate.value = null
        playNotificationSound({ frequency: 1180, duration: 90 })
    } catch (error: any) {
        const errors = error?.response?.data?.errors
        notify({
            title: trans("Could not assign barcode"),
            text: errors ? Object.values(errors).flat().join(" ") : (error?.response?.data?.message ?? trans("Please try again.")),
            type: "error",
        })
    } finally {
        isAssigning.value = false
    }
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="mx-auto w-full max-w-lg px-3 py-3 space-y-3">
        <div class="relative">
            <input
                ref="inputElement"
                v-model="buffer"
                type="text"
                inputmode="none"
                autocomplete="off"
                spellcheck="false"
                :placeholder="trans('Scan a SKO barcode')"
                style="padding-left:3rem"
                class="w-full rounded-xl border-2 border-gray-300 bg-white py-4 pr-12 font-mono text-lg tracking-wide focus:border-indigo-500 focus:ring-indigo-500"
                @keydown.enter.prevent="flushBuffer"
                @keydown.esc.prevent="clearBuffer"
                @input="registerKeystroke"
            />
            <FontAwesomeIcon icon="fal fa-barcode-read" class="absolute left-4 top-1/2 -translate-y-1/2 text-xl text-gray-400" fixed-width aria-hidden="true" />
            <span v-if="isScanning" class="absolute right-4 top-1/2 -translate-y-1/2 text-indigo-500">
                <LoadingIcon fixed-width aria-hidden="true" />
            </span>
        </div>

        <div class="flex items-center justify-between text-sm text-gray-500">
            <span>{{ trans('Checked') }}: <span class="font-medium tabular-nums text-gray-700">{{ okCount }}</span></span>
            <button
                v-if="mode === 'scan' && can_edit && !result"
                type="button"
                class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-indigo-600 active:bg-indigo-50"
                @click="openSearch"
            >
                <FontAwesomeIcon icon="fal fa-search" fixed-width aria-hidden="true" />
                {{ trans('Find a SKO') }}
            </button>
        </div>

        <template v-if="mode === 'scan'">
            <div v-if="result && !result.found" class="rounded-xl border-2 border-red-300 bg-red-50 p-4">
                <div class="flex items-center gap-3 text-red-800">
                    <FontAwesomeIcon icon="fal fa-times-circle" class="text-3xl text-red-500" fixed-width aria-hidden="true" />
                    <div>
                        <div class="font-semibold">{{ trans('Barcode not found') }}</div>
                        <div class="font-mono text-sm">{{ result.barcode }}</div>
                    </div>
                </div>
                <div class="mt-4 grid gap-2">
                    <button
                        v-if="can_edit"
                        type="button"
                        class="flex min-h-14 w-full items-center justify-center gap-2 rounded-xl bg-indigo-600 text-base font-semibold text-white active:bg-indigo-700"
                        @click="openSearch"
                    >
                        <FontAwesomeIcon icon="fal fa-search" fixed-width aria-hidden="true" />
                        {{ trans('Assign to a SKO') }}
                    </button>
                    <button
                        type="button"
                        class="flex min-h-12 w-full items-center justify-center gap-2 rounded-xl border-2 border-gray-300 bg-white text-base font-medium text-gray-700 active:bg-gray-100"
                        @click="result = null"
                    >
                        {{ trans('Skip') }}
                    </button>
                </div>
            </div>

            <div v-else-if="result?.org_stock" class="rounded-xl border-2 bg-white p-4" :class="justAssigned ? 'border-green-400' : 'border-gray-200'">
                <div v-if="justAssigned" class="mb-3 flex items-center gap-2 rounded-lg bg-green-50 px-3 py-2 text-sm font-medium text-green-800">
                    <FontAwesomeIcon icon="fal fa-check-circle" class="text-green-600" fixed-width aria-hidden="true" />
                    {{ trans('Barcode assigned') }}
                </div>

                <div class="flex gap-4">
                    <div class="h-28 w-28 shrink-0 overflow-hidden rounded-lg bg-gray-100">
                        <Image v-if="result.org_stock.image" :src="result.org_stock.image" class="h-full w-full object-contain" />
                        <div v-else class="flex h-full w-full items-center justify-center text-gray-300">
                            <FontAwesomeIcon icon="fal fa-box" class="text-3xl" fixed-width aria-hidden="true" />
                        </div>
                    </div>
                    <div class="min-w-0 flex-1">
                        <Link :href="route(result.org_stock.route.name, result.org_stock.route.parameters)" class="font-mono text-lg font-semibold text-gray-900">
                            {{ result.org_stock.code }}
                        </Link>
                        <div class="text-sm text-gray-700">{{ result.org_stock.name }}</div>
                        <div class="mt-1 flex items-center gap-1.5 text-xs text-gray-500">
                            <FontAwesomeIcon :icon="result.org_stock.state.icon" :class="result.org_stock.state.class" fixed-width aria-hidden="true" />
                            {{ result.org_stock.state.tooltip }}
                            <span v-if="result.org_stock.packed_in">· {{ trans('Pack of :n', { n: result.org_stock.packed_in }) }}</span>
                        </div>
                        <div class="mt-1 font-mono text-xs text-gray-500">
                            {{ result.matched_on === 'unit' ? trans('Unit EAN') : trans('SKO') }} {{ result.barcode }}
                        </div>
                    </div>
                </div>

                <div class="mt-4 rounded-lg bg-gray-50 p-3">
                    <div class="flex items-center justify-between text-sm">
                        <span class="flex items-center gap-1.5 text-gray-500">
                            <FontAwesomeIcon icon="fal fa-inventory" fixed-width aria-hidden="true" />
                            {{ trans('Stock') }}
                        </span>
                        <span class="font-semibold tabular-nums text-gray-900">{{ result.org_stock.quantity_in_locations }}</span>
                    </div>
                    <div v-if="result.org_stock.locations.length" class="mt-2 divide-y divide-gray-200">
                        <div v-for="location in result.org_stock.locations" :key="location.code" class="flex items-center justify-between py-1.5 text-sm">
                            <span class="font-mono font-medium text-gray-700">{{ location.code }}</span>
                            <span class="tabular-nums text-gray-700">{{ location.quantity }}</span>
                        </div>
                    </div>
                    <div v-else class="mt-2 text-sm text-gray-400">{{ trans('Not in any location') }}</div>
                </div>

                <div class="mt-4 grid gap-2">
                    <button
                        type="button"
                        class="flex min-h-14 w-full items-center justify-center gap-2 rounded-xl bg-green-600 text-base font-semibold text-white active:bg-green-700"
                        @click="confirmOk"
                    >
                        <FontAwesomeIcon icon="fal fa-check" fixed-width aria-hidden="true" />
                        {{ trans('All OK') }}
                    </button>
                    <button
                        v-if="can_edit && !justAssigned"
                        type="button"
                        class="flex min-h-12 w-full items-center justify-center gap-2 rounded-xl border-2 border-amber-400 bg-white text-base font-medium text-amber-800 active:bg-amber-50"
                        @click="openSearch"
                    >
                        <FontAwesomeIcon icon="fal fa-exchange" fixed-width aria-hidden="true" />
                        {{ trans('Wrong SKO, move barcode') }}
                    </button>
                </div>
            </div>

            <div v-else class="rounded-xl border-2 border-dashed border-gray-300 p-8 text-center text-gray-400">
                <FontAwesomeIcon icon="fal fa-barcode-read" class="text-4xl" fixed-width aria-hidden="true" />
                <div class="mt-2 text-sm">{{ trans('Scan a SKO to check it') }}</div>
            </div>
        </template>

        <template v-else>
            <div class="rounded-xl border-2 border-gray-200 bg-white p-3">
                <div class="mb-2 flex items-center gap-2">
                    <button type="button" class="flex h-11 w-11 items-center justify-center rounded-lg text-gray-500 active:bg-gray-100" @click="closeSearch">
                        <FontAwesomeIcon icon="fal fa-arrow-left" fixed-width aria-hidden="true" />
                    </button>
                    <div class="min-w-0 flex-1 text-sm text-gray-600">
                        <template v-if="scannedBarcode">
                            {{ trans('Assign') }} <span class="font-mono font-semibold text-gray-900">{{ scannedBarcode }}</span> {{ trans('to') }}:
                        </template>
                        <template v-else>{{ trans('Find a SKO, then scan its barcode') }}</template>
                    </div>
                </div>

                <div class="relative">
                    <input
                        ref="searchInput"
                        v-model="searchQuery"
                        type="search"
                        autocomplete="off"
                        :placeholder="trans('SKO code or name')"
                        class="w-full rounded-xl border-2 border-gray-300 py-3 pl-11 pr-4 text-base focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <FontAwesomeIcon icon="fal fa-search" class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400" fixed-width aria-hidden="true" />
                    <span v-if="isSearching" class="absolute right-4 top-1/2 -translate-y-1/2 text-indigo-500"><LoadingIcon /></span>
                </div>

                <div v-if="candidate" class="mt-3 rounded-xl border-2 border-indigo-300 bg-indigo-50 p-3">
                    <div class="font-mono text-lg font-semibold text-gray-900">{{ candidate.code }}</div>
                    <div class="text-sm text-gray-700">{{ candidate.name }}</div>
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            class="flex min-h-14 items-center justify-center gap-2 rounded-xl border-2 border-gray-300 bg-white text-base font-medium text-gray-700 active:bg-gray-100"
                            @click="candidate = null"
                        >
                            <FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
                            {{ trans('Cancel') }}
                        </button>
                        <button
                            type="button"
                            :disabled="isAssigning || !scannedBarcode"
                            class="flex min-h-14 items-center justify-center gap-2 rounded-xl bg-indigo-600 text-base font-semibold text-white active:bg-indigo-700 disabled:opacity-50"
                            @click="assign"
                        >
                            <LoadingIcon v-if="isAssigning" />
                            <FontAwesomeIcon v-else icon="fal fa-check" fixed-width aria-hidden="true" />
                            {{ trans('Assign') }}
                        </button>
                    </div>
                    <div v-if="!scannedBarcode" class="mt-2 text-center text-xs text-gray-500">{{ trans('Scan the barcode now to assign it') }}</div>
                </div>

                <ul v-else class="mt-2 divide-y divide-gray-100">
                    <li v-for="row in searchRows" :key="row.id">
                        <button
                            type="button"
                            class="flex min-h-14 w-full items-center justify-between gap-3 px-2 text-left active:bg-indigo-50"
                            @click="candidate = row"
                        >
                            <div class="min-w-0">
                                <div class="font-mono font-semibold text-gray-900">{{ row.code }}</div>
                                <div class="truncate text-sm text-gray-600">{{ row.name }}</div>
                            </div>
                            <div class="shrink-0 text-sm tabular-nums text-gray-500">{{ row.quantity_in_locations }}</div>
                        </button>
                    </li>
                    <li v-if="!searchRows.length && searchQuery.trim().length >= 2 && !isSearching" class="py-6 text-center text-sm text-gray-400">
                        {{ trans('No SKOs match') }}
                    </li>
                </ul>
            </div>
        </template>
    </div>
</template>
