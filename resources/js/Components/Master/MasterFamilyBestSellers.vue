<script setup lang="ts">
import { inject } from "vue"
import { Link } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTrophy, faImage } from "@fal"
import Image from "@common/Components/Image.vue"
import { trans } from "laravel-vue-i18n"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { routeType } from "@/types/route"

library.add(faTrophy, faImage)

const props = defineProps<{
    data: {
        products: {
            id: number
            slug: string
            code: string
            name: string
            image_thumbnail?: any
            master_prices: Record<string, { value: string | number, independent: boolean }>
            sales: number
        }[]
        currency: string
        major_currencies: {
            currency: string
            currency_symbol: string
            fraction_digits: number
        }[]
        route: routeType
    }
}>()

const locale = inject("locale", aikuLocaleStructure)

const rankColor = (index: number) => {
    return ["text-amber-500", "text-gray-400", "text-amber-700"][index] ?? "text-gray-400"
}
</script>

<template>
    <div v-if="props.data?.products?.length" class="bg-white border border-gray-200 rounded-lg p-4">
        <div class="flex items-center gap-2 mb-3 font-semibold text-gray-700">
            <FontAwesomeIcon :icon="faTrophy" class="text-amber-500" fixed-width aria-hidden="true" />
            {{ trans("Best sellers") }}
            <span class="text-xs font-normal text-gray-400">{{ trans("last 3 months") }}</span>
        </div>

        <div class="divide-y divide-gray-100">
            <Link
                v-for="(product, index) in props.data.products"
                :key="product.id"
                :href="route(props.data.route.name, { ...props.data.route.parameters, masterProduct: product.slug })"
                class="flex items-center gap-3 py-2 hover:bg-gray-50 rounded"
            >
                <div class="w-5 text-center font-bold" :class="rankColor(index)">
                    {{ index + 1 }}
                </div>

                <Image
                    v-if="product.image_thumbnail?.main?.thumbnail"
                    :src="product.image_thumbnail.main.thumbnail"
                    class="w-10 h-10 object-cover rounded"
                />
                <div v-else class="w-10 h-10 flex items-center justify-center bg-gray-100 rounded">
                    <FontAwesomeIcon :icon="faImage" class="text-gray-400" aria-hidden="true" />
                </div>

                <div class="min-w-0 flex-1">
                    <div class="truncate text-sm font-medium text-gray-800">{{ product.name || product.code }}</div>
                    <div class="text-xs text-gray-500">{{ product.code }}</div>
                    <div v-if="props.data.major_currencies?.length" class="flex flex-wrap gap-x-3 gap-y-0.5 text-xs">
                        <span
                            v-for="majorCurrency in props.data.major_currencies"
                            :key="majorCurrency.currency"
                            class="inline-flex items-center gap-1"
                        >
                            <span class="text-gray-400">{{ majorCurrency.currency }}</span>
                            <span class="font-medium text-gray-700">
                                {{ locale.currencyFormat(majorCurrency.currency, Number(product.master_prices?.[majorCurrency.currency]?.value ?? 0)) }}
                            </span>
                        </span>
                    </div>
                </div>

                <div class="text-sm font-semibold text-gray-700 whitespace-nowrap">
                    {{ locale.currencyFormat(props.data.currency, product.sales) }}
                </div>
            </Link>
        </div>
    </div>
</template>
