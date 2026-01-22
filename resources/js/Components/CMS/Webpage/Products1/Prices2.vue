<script setup lang="ts">
import { useLocaleStore } from "@/Stores/locale"
import { inject, ref } from "vue"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"
import { Image as ImageTS } from "@/types/Image"
import { trans } from "laravel-vue-i18n"
import Discount from "@/Components/Utils/Label/Discount.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPlusCircle, faQuestionCircle } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import AvailableGROfferLabel from "@/Components/Utils/Iris/AvailableGROfferLabel.vue"
import { Popover } from "primevue"
library.add(faPlusCircle, faQuestionCircle)

const layout = inject("layout", retinaLayoutStructure)
const locale = useLocaleStore()

interface ProductResource {
    id: number
    name: string
    code: string
    image?: {
        source: ImageTS,
    }
    rpp?: number
    unit: string
    stock: number
    rating: number
    price: number
    url: string | null
    units: number
    bestseller?: boolean
    is_favourite?: boolean
    exist_in_portfolios_channel: number[]
    is_exist_in_all_channel: boolean
    top_seller: number | null
    web_images: {
        main: {
            original: ImageTS,
            gallery: ImageTS
        }
    }
}

defineProps<{
    product: ProductResource
    currency?: {
        code: string
        name: string
    }
    basketButton?: boolean
}>()

const isGoldMember = false // TO DO: get from user data

const _popoverQuestionCircle = ref<InstanceType<any> | null>(null)
</script>

<template>
    <div class="border-t xborder-b border-gray-200 p-1 px-0 mb-1 flex flex-col gap-1 tabular-nums text-sm">
        <!-- <Discount v-if="Object.keys(product.offers_data || {})?.length" :offers_data="product.offers_data" class="text-xxs w-full justify-center" /> -->
        <div>
            <div class="flex justify-between">
                <div>
                    <div class="text-xs">{{ trans("Price") }} ({{ trans("Excl. Vat") }})</div>
                    <div v-if="product.units == 1" class="font-bold text-base">
                        {{ locale.currencyFormat(currency?.code, product.price) }}/<span class="font-normal">{{ product.unit}}</span>
                    </div>
                    <div v-else class="font-bold text-base">
                        {{ locale.currencyFormat(currency?.code, product.price) }} <span v-if="product.price_per_unit > 0">({{ locale.currencyFormat(currency?.code, product.price_per_unit || 0) }}/<span class="font-normal">{{ product.unit}}</span>)</span>
                    </div>
                </div>

                <div v-if="product?.rrp_per_unit > 0" v-tooltip="trans('Recommended retail price')" class="flex flex-col text-right">
                    <div class="text-xs">{{ trans("RRP") }} ({{ trans("Excl. Vat") }}):</div>
                    <div class="font-bold text-xs">
                        {{ locale.currencyFormat(currency?.code, product?.rrp_per_unit || 0) }}/<span class="font-normal">{{ product.unit}}</span>
                    </div>
                </div>
            </div>

            <!-- Price: Gold Member -->
            <div class="text-orange-500 font-bold text-sm">
                <span v-if="product.units == 1">
                    {{ locale.currencyFormat(currency?.code, product.gr_price) }}/<span class="font-normal">{{ product.unit }}</span>
                </span>
                <span v-else>
                    {{ locale.currencyFormat(currency?.code, product.gr_price) }} ({{ locale.currencyFormat(currency?.code, product.gr_price_per_unit) }}/<span class="font-normal">{{ product.unit }}</span>)
                </span>
            </div>

            <!-- Section: Profit, label Gold Reward Member -->
            <div class="mt-4 flex justify-between">
                <div v-if="layout?.user?.gr_data?.customer_is_gr" class="relative w-fit">
                    <div class="bg-orange-400 rounded px-2 py-0.5 text-xxs w-fit text-white">{{ trans("Member Price") }}</div>
                    <img src="/assets/promo/gr.png" alt="Gold Reward logo" class="absolute -right-9 -top-1 inline-block h-10 ml-1 align-middle" />
                </div>
                <div v-else class="relative w-fit">
                    <div class="bg-gray-400 rounded px-2 py-0.5 text-xxs w-fit text-white">{{ trans("Member Price") }}</div>
                    <div class="my-1.5 text-xs">
                        {{ trans("NOT A MEMBER") }}? <span @click="_popoverQuestionCircle?.toggle" @mouseenter="_popoverQuestionCircle?.show" @mouseleave="_popoverQuestionCircle?.hide" class="cursor-pointer">
                            <FontAwesomeIcon icon="fal fa-question-circle" class="" fixed-width aria-hidden="true" />
                        </span>
                    </div>
                    
                    <AvailableGROfferLabel
                        v-if="
                            (product.stock && basketButton && !product.is_coming_soon)  // same as button add to basket conditions
                            && product.available_gr_offer_to_use?.trigger_data?.item_quantity
                            && !layout?.user?.gr_data?.customer_is_gr
                            && product.quantity_ordered_new < product.available_gr_offer_to_use.trigger_data.item_quantity
                        "
                        :product
                    />
                </div>

                <!-- Section: Profit -->
                <div v-if="product?.margin" class="flex justify-end text-right flex-col">
                    <div>
                        <FontAwesomeIcon icon="fal fa-plus-circle" class="" fixed-width aria-hidden="true" />
                        {{ trans("Profit") }}:
                    </div>
                    <div class="font-bold text-green-700 text-base">
                        ({{ product?.margin }})
                    </div>
                    <div class="italic">
                        {{ locale.currencyFormat(currency?.code, product?.profit_per_unit || 0) }}/{{ product.unit }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Popover: Question circle GR member -->
        <Popover ref="_popoverQuestionCircle" :style="{width: '250px'}" class="py-1 px-2">
            <div class="text-xs">
                <p class="font-bold mb-4">{{ trans("VOLUME DISCOUNT") }}</p>
                <p class="inline-block mb-4 text-justify">
                    {{ trans("You don't need Gold Reward status to access the lower price") }}.
                </p>
                <p class="mb-4 text-justify">
                    {{ trans("Order the listed volume and the member price applies automatically at checkout") }}. {{ trans("The volume can be made up from the whole product family, not just the same item") }}.
                </p>
            </div>
        </Popover>

    </div>
</template>


<style scoped></style>