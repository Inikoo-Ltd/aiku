<!--
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faAtom, faBox, faCube, faCloudRainbow } from "@fal"
import { faOctopusDeploy } from "@fortawesome/free-brands-svg-icons"
import { routeType } from "@/types/route"

library.add(faAtom, faBox, faCube, faCloudRainbow, faOctopusDeploy)

type CompositionRow = {
    code: string
    quantity: number
    org_code?: string
    shop_code?: string
    route?: routeType
}

// The triangle seen from its centre: read only, every row links to the page where
// that leg is actually edited.
const props = defineProps<{
    data: {
        stocks: CompositionRow[]
        org_stocks: CompositionRow[]
        master_products: CompositionRow[]
        products: CompositionRow[]
    }
    tab: string
}>()

const sections = [
    { key: 'stocks', label: trans('Packed in SKOs'), icon: 'fal fa-cloud-rainbow', unit: trans('per pack') },
    { key: 'org_stocks', label: trans('Packed per warehouse'), icon: 'fal fa-box', unit: trans('per pack') },
    { key: 'master_products', label: trans('Sold as master products'), icon: 'fab fa-octopus-deploy', unit: trans('per outer') },
    { key: 'products', label: trans('Sold as products'), icon: 'fal fa-cube', unit: trans('per outer') },
] as const
</script>

<template>
    <div class="max-w-5xl px-4 py-5 sm:px-6 lg:px-8 grid gap-6 sm:grid-cols-2">
        <section v-for="section in sections" :key="section.key" class="rounded-lg border border-gray-200 bg-white">
            <div class="border-b border-gray-100 px-4 py-3 flex items-center gap-2">
                <FontAwesomeIcon :icon="section.icon" class="text-gray-400" fixed-width aria-hidden="true" />
                <h2 class="font-medium text-gray-700">{{ section.label }}</h2>
                <span class="text-xs text-gray-400">({{ (data[section.key] || []).length }})</span>
            </div>
            <div class="px-4 py-3 text-sm max-h-72 overflow-y-auto" style="scrollbar-width: thin">
                <div v-if="!(data[section.key] || []).length" class="text-gray-400 italic">
                    {{ trans('None') }}
                </div>
                <div v-for="(row, index) in data[section.key]" :key="index" class="flex items-baseline gap-2 py-0.5">
                    <span v-if="row.org_code" class="w-12 uppercase text-gray-400">{{ row.org_code }}</span>
                    <span v-if="row.shop_code" class="w-12 uppercase text-gray-400">{{ row.shop_code }}</span>
                    <Link v-if="row.route" :href="route(row.route.name, row.route.parameters)" class="primaryLink">
                        {{ row.code }}
                    </Link>
                    <span v-else class="text-gray-700">{{ row.code }}</span>
                    <span class="text-gray-500">× {{ row.quantity }} {{ section.unit }}</span>
                </div>
            </div>
        </section>
    </div>
</template>
