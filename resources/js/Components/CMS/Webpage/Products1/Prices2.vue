<script setup lang="ts">
import { useLocaleStore } from "@/Stores/locale"
import { inject, ref } from "vue"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"
import { Image as ImageTS } from "@/types/Image"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPlusCircle, faQuestionCircle } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { Popover } from "primevue"
import MemberPriceLabel from "@/Components/Utils/Iris/Family/MemberPriceLabel.vue"
import NonMemberPriceLabel from "@/Components/Utils/Iris/Family/NonMemberPriceLabel.vue"
import ProfitCalculationList from "@/Components/Utils/Iris/ProfitCalculationList.vue"
import DiscountByType from "@/Components/Utils/Label/DiscountByType.vue"

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


const _popoverProfit = ref(null)
</script>

<template>
    <div class="font-sans border-t xborder-b border-gray-200 mt-1 p-1 px-0 mb-1 flex flex-col gap-1 tabular-nums text-sm">
        <div>
            <div class="flex justify-between">
                <div>
                    <div class="text-xs">{{ trans("Price") }} <span class="text-gray-500 text-xxs">({{ trans("Excl. Vat") }})</span></div>
                    <div v-if="product.units == 1" class="font-bold text-sm leading-4">
                        {{ locale.currencyFormat(currency?.code, product.price) }}/<span class="font-normal">{{ product.unit}}</span>
                    </div>
                    <div v-else class="font-bold text-base leading-4 text-sm">
                        {{ locale.currencyFormat(currency?.code, product.price) }} <span v-if="product.price_per_unit > 0">({{ locale.currencyFormat(currency?.code, product.price_per_unit || 0) }}/<span class="font-normal">{{ product.unit}}</span>)</span>
                    </div>
                </div>

                <div v-if="product?.rrp_per_unit > 0" v-tooltip="trans('Recommended retail price')+' ('+trans('Excl. Vat')+')'" class="flex flex-col text-right">
                    <div class="text-xs">{{ trans("RRP") }}:</div>
                    <div class="font-bold text-xs">
                        {{ locale.currencyFormat(currency?.code, product?.rrp_per_unit || 0) }}/<span class="font-normal">{{ product.unit}}</span>
                    </div>
                </div>
            </div>

            <!-- Price: Gold Member -->
            <div v-if="product.discounted_price" class="text-orange-500 font-bold text-sm">
                <span v-if="product.units == 1">
                    {{ locale.currencyFormat(currency?.code, product.discounted_price) }}/<span class="font-normal">{{ product.unit }}</span>
                </span>
                <span v-else>
                    {{ locale.currencyFormat(currency?.code, product.discounted_price) }} ({{ locale.currencyFormat(currency?.code, product.discounted_price_per_unit) }}/<span class="font-normal">{{ product.unit }}</span>)
                </span>
            </div>


            <!-- Section: Profit, label Gold Reward Member -->
            <div class="mt-0 flex justify-between gap-x-2">
                <template v-if="product.product_offers_data?.number_offers > 0">
                    <div v-if="getBestOffer(product?.product_offers_data?.best_percentage_off?.offer_id)?.type === 'Category Quantity Ordered Order Interval'"
                        class="flex flex-col w-fit"
                    >
                        <MemberPriceLabel
                            v-if="layout?.user?.gr_data?.customer_is_gr"
                            :offer="getBestOffer(product?.product_offers_data?.best_percentage_off?.offer_id)"
                        />
                        <NonMemberPriceLabel v-else
                            :product
                        />
        
                      <!--   <AvailableVolOfferLabel class="w-48"
                            v-if="
                                (product.stock && basketButton && !product.is_coming_soon)  // same as button add to basket conditions
                                && !layout?.user?.gr_data?.customer_is_gr"
                            :offer="getBestOffer(product?.product_offers_data?.best_percentage_off?.offer_id)"
                        /> -->
                        <DiscountByType v-if="(product.stock && basketButton && !product.is_coming_soon)"  :offers_data="product?.product_offers_data" />
                    </div>
                    <div v-else />
                </template>

                <div v-else />

                <!-- Section: Profit -->
                <div v-if="product?.discounted_profit" class="flex justify-end text-right flex-col text-xs">
                    <div class="whitespace-nowrap">
                        <span @click="_popoverProfit?.toggle" @mouseenter="_popoverProfit?.show" @mouseleave="_popoverProfit?.hide"
                            class="ml-1 cursor-pointer opacity-60 hover:opacity-100"
                        >
                            <FontAwesomeIcon icon="fal fa-plus-circle" class="" fixed-width aria-hidden="true" />
                        </span>
                        {{ trans("Profit") }}:
                    </div>
                    <div class="font-bold text-green-700 text-xxs">
                        ({{ product?.margin }})
                    </div>
                    <div class="italic text-xxs">
                        <span class="xtext-green-600">{{ locale.currencyFormat(currency?.code, product?.discounted_profit_per_unit || 0) }}</span>/{{ product.unit }}
                    </div>



                    <!-- Popover: Question circle GR member -->
                    <Popover ref="_popoverProfit" :style="{ width: '450px' }" class="py-1 px-2 text-xxs">
                        <ProfitCalculationList :product="product" />
                    </Popover>
                </div>

            </div>

        </div>

    </div>
</template>
