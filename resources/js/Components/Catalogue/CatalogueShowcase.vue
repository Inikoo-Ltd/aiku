<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 13 Oct 2022 15:35:22 Central European Summer Time, Malaga - East Midlands UK
  - Copyright (c) 2022, Raul A Perusquia Flores
-->

<script setup lang="ts">
import { computed, inject } from "vue"
import { trans } from "laravel-vue-i18n"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCubes, faFolderDownload, faMailBulk, faSeedling } from "@fal"
import { faFireAlt } from "@fad"
import { faCheckCircle, faTimesCircle, faExclamationTriangle } from "@fas"
import Image from "@/Components/Image.vue"
import StatsBox from "@/Components/Stats/StatsBox.vue"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { Image as ImageProxy } from "@/types/Image"

library.add(
    faCheckCircle,
    faTimesCircle,
    faCubes,
    faSeedling,
    faFireAlt,
    faExclamationTriangle,
    faFolderDownload,
    faMailBulk,
)

interface TopSelling {
    product: {
        value: {
            id: number
            name: string
            code: string
            images: {
                data: { source: ImageProxy }[]
            }
            sold_on_month: number
            stock: number
            price: number
        }
    }
    family: {
        value: {
            id: number
            name: string
        }
        icon: string
    }
    department: {
        value: {
            id: number
            name: string
            current_families: number
            current_products: number
        }
    }
}

const props = defineProps<{
    data: {
        stats: any
        top_selling: TopSelling
    }
}>()

const layout = inject("layout", layoutStructure)

const hasTopSelling = computed(() =>
    props.data.top_selling?.product?.value ||
    props.data.top_selling?.department?.value ||
    props.data.top_selling?.family?.value,
)
</script>

<template>
    <!-- Stats Grid -->
    <div class="p-6">
        <dl class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4 lg:gap-5">
            <StatsBox
                v-for="(stat, index) in data.stats"
                :key="index"
                :stat="stat"
            />
        </dl>
    </div>

    <!-- Top of the Month -->
    <div v-if="hasTopSelling" class="p-6">
        <div class="border-b border-gray-200 py-1 text-xl font-semibold">
            {{ trans("Top of the month") }}
        </div>

        <dl class="isolate mt-4 grid h-72 grid-cols-1 gap-5 sm:grid-cols-2 sm:grid-rows-2">
            <!-- Product of the Month -->
            <div v-if="data.top_selling.product.value" class="example-2 row-span-2 rounded-md">
                <div
                    class="inner group flex h-full gap-x-4 rounded-md bg-gray-100 px-8 py-8"
                    :style="{ background: `color-mix(in srgb, ${layout?.app?.theme[0]} 10%, white)` }"
                >
                    <!-- Product Image -->
                    <div class="aspect-square h-1/2 w-fit flex-shrink-0 overflow-hidden rounded-md lg:h-full">
                        <Image :src="data.top_selling.product.value?.images?.data?.[0]?.source" />
                    </div>

                    <!-- Product Info -->
                    <div class="flex flex-col justify-between gap-y-1">
                        <div>
                            <div class="animate-pulse text-sm text-indigo-600">
                                {{ trans("Product of the month") }}
                            </div>
                            <h3 class="text-xl font-semibold">
                                {{ data.top_selling.product.value?.name }}
                            </h3>
                            <div class="text-sm text-gray-400">
                                {{ data.top_selling.product.value?.code || "-" }}
                            </div>
                        </div>

                        <div>
                            <p class="text-gray-500">
                                {{ trans("Sold this month") }}: {{ data.top_selling.product.value?.sold_on_month || "-" }}
                            </p>
                            <p class="text-gray-500">
                                {{ trans("Stock") }}: {{ data.top_selling.product.value?.stock || "-" }}
                            </p>
                            <p class="text-gray-500">
                                {{ trans("Price") }}: {{ data.top_selling.product.value?.price || "-" }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Department of the Month -->
            <div
                v-if="data.top_selling.department.value"
                class="flex items-center rounded-md border border-gray-200 bg-gray-50 p-6"
            >
                <div class="flex items-center gap-x-2">
                    <div class="rounded p-3">
                        <FontAwesomeIcon
                            icon="fal fa-folder-tree"
                            class="text-xl text-indigo-500"
                            v-tooltip="trans('Department')"
                            fixed-width
                            aria-hidden="true"
                        />
                    </div>
                    <div>
                        <div class="text-xl font-medium">
                            {{ data.top_selling.department.value.name }}
                        </div>
                        <div class="flex gap-x-10">
                            <span class="text-gray-500">
                                <FontAwesomeIcon icon="fal fa-folder" class="text-gray-400" fixed-width aria-hidden="true" />
                                {{ data.top_selling.department.value.current_families }}
                            </span>
                            <span class="text-gray-500">
                                <FontAwesomeIcon icon="fal fa-cube" class="text-gray-400" fixed-width aria-hidden="true" />
                                {{ data.top_selling.department.value.current_products }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Family of the Month -->
            <div
                v-if="data.top_selling.family.value"
                class="flex items-center rounded-md border border-gray-200 bg-gray-50 p-6"
            >
                <div class="flex items-center gap-x-2">
                    <div class="rounded p-3">
                        <FontAwesomeIcon
                            :icon="data.top_selling.family.icon"
                            class="text-xl text-indigo-500"
                            v-tooltip="trans('Family')"
                            fixed-width
                            aria-hidden="true"
                        />
                    </div>
                    <div class="text-xl font-medium">
                        {{ data.top_selling.family.value.name }}
                    </div>
                </div>
            </div>
        </dl>
    </div>
</template>

<style lang="scss">
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
