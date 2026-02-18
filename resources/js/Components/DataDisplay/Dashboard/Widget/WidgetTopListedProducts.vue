<script setup lang="ts">
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faInfoCircle } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"

library.add(faInfoCircle)

const props = defineProps<{
    products: Array<{
        id: number
        code: string
        name: string
        total_listed: number
    }>
}>()

const topProduct = props.products?.[0]
</script>

<template>
    <div class="flex justify-between gap-x-4 px-4 py-5 sm:p-6 rounded-lg bg-gray-50 border border-gray-200 tabular-nums">
        <dd class="flex flex-col gap-x-2">
            <div class="text-base mb-1 text-gray-400">
                {{ trans('Top Listed Product') }}
                <FontAwesomeIcon
                    v-tooltip="trans('Product most frequently listed in portfolios (All time)')"
                    :icon="faInfoCircle"
                    class="hover:text-gray-600"
                    fixed-width
                    aria-hidden="true"
                />
            </div>
            <div v-if="topProduct" class="flex flex-col gap-y-2 leading-none items-baseline text-2xl font-semibold text-org-500">
                <div class="flex gap-x-2 items-end">
                    {{ topProduct.code }}
                </div>
                <div class="text-sm text-gray-500 flex gap-x-2 items-center">
                    <span v-if="topProduct.name" class="font-normal">{{ topProduct.name }}</span>
                    <span class="text-gray-400">•</span>
                    <span class="font-semibold">{{ topProduct.total_listed }} {{ trans('listings') }}</span>
                </div>
            </div>
            <div v-else class="text-sm text-gray-400">
                {{ trans('No data available') }}
            </div>
        </dd>
    </div>
</template>
