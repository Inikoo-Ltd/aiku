<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 13 Oct 2022 15:35:22 Central European Summer Time, Malaga - East Midlands UK
  - Copyright (c) 2022, Raul A Perusquia Flores
-->

<script setup lang="ts">
import { computed, inject } from "vue"
import { Link } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCubes, faFolderDownload, faMailBulk, faMedal, faSeedling } from "@fal"
import { faFireAlt } from "@fad"
import { faCheckCircle, faTimesCircle, faExclamationTriangle, faHatCowboy } from "@fas"
import Image from "@common/Components/Image.vue"
import StatsBox from "@/Components/Stats/StatsBox.vue"
import StatsBoxNegativeList from "@/Components/Stats/StatsBoxNegativeList.vue"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { Image as ImageProxy } from "@/types/Image"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"

library.add(
    faCheckCircle,
    faTimesCircle,
    faCubes,
    faSeedling,
    faFireAlt,
    faExclamationTriangle,
    faFolderDownload,
    faMailBulk,
    faHatCowboy,
    faMedal
)

interface RouteTarget {
    name: string
    parameters: Record<string, string>
}

interface TopSelling {
    product: {
        value: {
            id: number
            name: string
            code: string
            web_images?: { main?: { original?: ImageProxy } }
            sold_on_month: number
            available_quantity: number
            price: number
        } | null
        route: RouteTarget | null
    }
    family: {
        value: {
            id: number
            name: string
        } | null
        icon: string
        route: RouteTarget | null
        counts: { products: number } | null
    }
    department: {
        value: {
            id: number
            name: string
        } | null
        route: RouteTarget | null
        counts: { families: number, products: number } | null
    }
}

const props = defineProps<{
    tab?: string
    data: {
        stats: any
        top_selling: TopSelling
        currency_code: string
    }
}>()

const layout = inject("layout", layoutStructure)
const locale = inject('locale', aikuLocaleStructure)

const hasTopSelling = computed(() =>
    props.data.top_selling?.product?.value ||
    props.data.top_selling?.department?.value ||
    props.data.top_selling?.family?.value,
)

const statsWithoutAdditional = Object.fromEntries(
  Object.entries(props.data.stats).filter(
    ([key]) => key !== 'additionalStatBox'
  )
);

const statsOnlyAdditional = Object.fromEntries(
  Object.entries(props.data.stats).filter(
    ([key]) => key === 'additionalStatBox'
  )
);

</script>

<template>
    <!-- Stats Grid -->
    <div class="p-6 !pb-0">
        <span class="font-semibold"> {{ ctrans('Catalogue') }} </span>
        <dl class="pt-2 grid grid-cols-1 gap-2 sm:grid-cols-3 xl:grid-cols-4 lg:gap-5">
            <StatsBox
                v-for="(stat, index) in statsWithoutAdditional"
                :key="index"
                :stat="stat"
            />
        </dl>
    </div>

    <div v-if="statsOnlyAdditional.additionalStatBox" class="p-6">
        <span class="font-semibold"> {{ ctrans('Faulty Catalogue') }} </span>
        <div class="pt-2 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 lg:gap-5 gap-2">
            <StatsBoxNegativeList :stats="statsOnlyAdditional.additionalStatBox" />
        </div>
    </div>

    <!-- Top of the Month -->
    <div v-if="hasTopSelling" class="p-6">
        <div class="border-b border-gray-200 py-1 text-xl font-semibold">
            {{ ctrans("Top of the month") }}
        </div>

        <dl class="mt-4 grid grid-cols-1 gap-5 xl:grid-cols-2 xl:grid-rows-2">
            <!-- Product of the Month -->
            <div v-if="data.top_selling.product.value" class="example-2 rounded-md xl:row-span-2">
                <div
                    class="inner flex h-full flex-col gap-4 rounded-md px-6 py-6 sm:flex-row sm:items-center"
                    :style="{ background: `color-mix(in srgb, ${layout?.app?.theme[0]} 10%, white)` }"
                >
                    <div class="aspect-square w-32 flex-shrink-0 overflow-hidden rounded-md sm:w-40">
                        <Image :src="data.top_selling.product.value?.web_images?.main?.original" />
                    </div>

                    <div class="flex min-w-0 flex-col gap-y-3">
                        <div>
                            <div class="animate-pulse text-sm text-[var(--theme-color-4)]">
                                {{ ctrans("Product of the month") }}
                            </div>
                            <component
                                :is="data.top_selling.product.route ? Link : 'span'"
                                :href="data.top_selling.product.route ? route(data.top_selling.product.route.name, data.top_selling.product.route.parameters) : undefined"
                                class="top-selling-link line-clamp-2 text-lg font-semibold xl:text-xl"
                            >
                                {{ data.top_selling.product.value?.name }}
                            </component>
                            <div class="text-sm text-gray-400">
                                {{ data.top_selling.product.value?.code || "-" }}
                            </div>
                        </div>

                        <div class="text-sm text-gray-500">
                            <p>{{ ctrans("Sold this month") }}: {{ data.top_selling.product.value?.sold_on_month || "n/a" }}</p>
                            <p>{{ ctrans("Available Quantity") }}: {{ data.top_selling.product.value?.available_quantity || "-" }}</p>
                            <p>{{ ctrans("Price") }}: {{ locale.currencyFormat(props.data.currency_code, data.top_selling.product.value?.price || 0) }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Department of the Month -->
            <div v-if="data.top_selling.department.value" class="flex items-center gap-x-3 rounded-md border border-gray-200 bg-gray-50 p-6">
                <FontAwesomeIcon icon="fal fa-folder-tree" class="text-xl text-[var(--theme-color-4)]" v-tooltip="ctrans('Department')" fixed-width aria-hidden="true" />
                <div class="min-w-0">
                    <component
                        :is="data.top_selling.department.route ? Link : 'span'"
                        :href="data.top_selling.department.route ? route(data.top_selling.department.route.name, data.top_selling.department.route.parameters) : undefined"
                        class="top-selling-link block truncate text-xl font-medium"
                    >
                        {{ data.top_selling.department.value.name }}
                    </component>
                    <div v-if="data.top_selling.department.counts" class="mt-1 flex gap-x-6 text-sm text-gray-500 tabular-nums">
                        <span v-tooltip="ctrans('Families')">
                            <FontAwesomeIcon icon="fal fa-folder" class="text-gray-400" fixed-width aria-hidden="true" />
                            {{ locale.number(data.top_selling.department.counts.families) }}
                        </span>
                        <span v-tooltip="ctrans('Products')">
                            <FontAwesomeIcon icon="fal fa-cube" class="text-gray-400" fixed-width aria-hidden="true" />
                            {{ locale.number(data.top_selling.department.counts.products) }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Family of the Month -->
            <div v-if="data.top_selling.family.value" class="flex items-center gap-x-3 rounded-md border border-gray-200 bg-gray-50 p-6">
                <FontAwesomeIcon :icon="data.top_selling.family.icon" class="text-xl text-[var(--theme-color-4)]" v-tooltip="ctrans('Family')" fixed-width aria-hidden="true" />
                <div class="min-w-0">
                    <component
                        :is="data.top_selling.family.route ? Link : 'span'"
                        :href="data.top_selling.family.route ? route(data.top_selling.family.route.name, data.top_selling.family.route.parameters) : undefined"
                        class="top-selling-link block truncate text-xl font-medium"
                    >
                        {{ data.top_selling.family.value.name }}
                    </component>
                    <div v-if="data.top_selling.family.counts" class="mt-1 flex gap-x-6 text-sm text-gray-500 tabular-nums">
                        <span v-tooltip="ctrans('Products')">
                            <FontAwesomeIcon icon="fal fa-cube" class="text-gray-400" fixed-width aria-hidden="true" />
                            {{ locale.number(data.top_selling.family.counts.products) }}
                        </span>
                    </div>
                </div>
            </div>
        </dl>
    </div>
</template>

<style lang="scss">
.top-selling-link {
    border-radius: 0.25rem;
    transition: color 0.15s;

    &[href]:hover {
        color: var(--theme-color-4);
        text-decoration: underline;
    }

    &[href]:focus-visible {
        outline: 2px solid var(--theme-color-4);
        outline-offset: 2px;
    }
}

.example-2 {
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1px;

    .inner {
        position: relative;
        z-index: 1;
        width: 100%;
        margin: 0;
    }

    &::before {
        content: "";
        display: block;
        background: linear-gradient(
            90deg,
            rgba(255, 255, 255, 0) 0%,
            v-bind('`color-mix(in srgb, ${layout?.app?.theme[0]} 100%, transparent)`') 50%,
            rgba(255, 255, 255, 0) 100%
        );
        height: 150%;
        width: 300px;
        position: absolute;
        top: 50%;
        transform-origin: top center;
        animation: rotate 3s linear infinite;
        z-index: 0;
    }
}

@keyframes rotate {
    from { transform: rotate(0deg); }
    to   { transform: rotate(360deg); }
}
</style>
