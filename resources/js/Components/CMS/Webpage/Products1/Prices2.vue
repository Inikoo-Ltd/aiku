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
import MemberPriceLabel from "@/Components/Utils/Iris/Family/MemberPriceLabel.vue"
import NonMemberPriceLabel from "@/Components/Utils/Iris/Family/NonMemberPriceLabel.vue"
import ProfitCalculationList from "@/Components/Utils/Iris/ProfitCalculationList.vue"
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

    discounted_price: number
    discounted_price_per_unit: number
    discounted_profit: number
    discounted_profit_per_unit: number
    discounted_margin: number

    product_offers_data: {
        number_offers: 1
        offers: {
            [key: string]: {
                state: string
                type: string
                label: string
                allowances: {
                    class: string
                    type: string
                    label: string
                    percentage_off: string
                }[]
                triggers_labels: string[]
                max_percentage_discount: string
            }
        }
        best_percentage_off: {
            percentage_off: string
            offer_id: number
        }
    }
}

const props = defineProps<{
    product: ProductResource
    currency?: {
        code: string
        name: string
    }
    basketButton?: boolean
}>()

const getBestOffer = (offerId: string) => {
    if (!offerId) {
        return
    }

    return Object.values(props.product?.product_offers_data?.offers || []).find(e => e.id == offerId)
}

// console.log('fffff', props.product.product_offers_data)

const _popoverProfit = ref(null)
</script>

<template>
    <div class="font-sans border-t xborder-b border-gray-200 mt-1 p-1 px-0 mb-1 flex flex-col gap-1 tabular-nums text-sm">

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
            <div v-if="product.discounted_price" class="text-orange-500 font-bold text-sm" >
                <span v-if="product.units == 1 &&  product.discounted_price">
                    {{ locale.currencyFormat(currency?.code, product.discounted_price) }}/<span class="font-normal">{{ product.unit }}</span>
                </span>
                <span v-else>
                    {{ locale.currencyFormat(currency?.code, product.discounted_price) }} ({{ locale.currencyFormat(currency?.code, product.discounted_price_per_unit) }}/<span class="font-normal">{{ product.unit }}</span>)
                </span>
            </div>


            <!-- Section: Profit, label Gold Reward Member -->
            <div class="mt-0 flex justify-between">

                <template v-if="product.product_offers_data?.number_offers > 0">
                    <template v-if="getBestOffer(product?.product_offers_data?.best_percentage_off?.offer_id)?.type === 'Category Quantity Ordered Order Interval'">
                        <MemberPriceLabel
                            v-if="layout?.user?.gr_data?.customer_is_gr"
                            :offer="getBestOffer(product?.product_offers_data?.best_percentage_off?.offer_id)"
                        />
                        <NonMemberPriceLabel v-else
                            :product
                            :isShowAvailableGROffer="
                                (product.stock && basketButton && !product.is_coming_soon)  // same as button add to basket conditions
                                && !layout?.user?.gr_data?.customer_is_gr
                            "
                        />
                    </template>
                    <div v-else />
                </template>

                <div v-else />

                <!-- Section: Profit -->
                <div v-if="product?.discounted_profit" class="flex justify-end text-right flex-col">
                    <div>                        
                        <span @click="_popoverProfit?.toggle" @mouseenter="_popoverProfit?.show" @mouseleave="_popoverProfit?.hide"
                            class="ml-1 cursor-pointer opacity-60 hover:opacity-100"
                        >
                            <FontAwesomeIcon icon="fal fa-plus-circle" class="" fixed-width aria-hidden="true" />
                        </span>
                        {{ trans("Profit") }}:
                    </div>
                    <div class="font-bold text-green-700 text-sm">
                        ({{ product?.margin }})
                    </div>
                    <div class="italic text-xs">
                        <span class="text-green-600">{{ locale.currencyFormat(currency?.code, product?.discounted_profit_per_unit || 0) }}</span>/{{ product.unit }}
                    </div>

                    <!-- <div class="flex justify-between items-end">
                        <span @click="_popoverProfit?.toggle">Profit</span>:
                        <span class="text-green-500 ml-1 font-bold">
                            {{ fieldValue.product?.discounted_margin ?? fieldValue.product?.margin }}
                        </span>
                    </div> -->

                    <!-- Popover: Question circle GR member -->
                    <Popover ref="_popoverProfit" :style="{ width: '450px' }" class="py-1 px-2 text-xxs">
                        <ProfitCalculationList :product="product" />
                    </Popover>
                </div>
                <!-- <div v-if="product?.margin" class="flex justify-end text-right flex-col">
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
                </div> -->
            </div>

        </div>

    </div>
</template>


<style scoped></style>