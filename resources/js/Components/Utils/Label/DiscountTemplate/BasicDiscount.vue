<script setup lang="ts">
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faBadgePercent, faMedal, faMoneyCheckEditAlt } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { computed } from "vue"
import { formatPercentage } from '@/Composables/Utils'
import { isShopOrderedSubTrigger } from '@/Composables/useOfferLabelVariant'
library.add(faBadgePercent, faMoneyCheckEditAlt)


const props = defineProps<{
    offers_data: {   // CalculateOrderDiscounts
        v: number
        o: {
            oc: number  // Offer Campaign id
            o: number  // Offer id
            oa: number  // Offer Allowance id
            t: string  // Type --- "percentage"
            p: string  // Percentage --- "10.0%"
            l: string  // Label
            st: string | null // Sub Trigger --- CalculateOrderDiscounts --- "a" => Gold Reward Amnesty, "i" => Gold Reward Member,  "q" => Quantity, "sd" => Step Discount, "so" => Shop Ordered, "fob" => First Order Bonus
            sto: string | null // Sub Trigger Offer Id
        }
    },
    is_discretionary_offer?: boolean
}>()

const isShopOffer = computed<boolean>(() => isShopOrderedSubTrigger(props.offers_data?.o?.st))



</script>

<template>
    <div v-if="is_discretionary_offer" class="bg-[#A80000] px-1 py-0.5 text-xs border flex items-center border-[#A80000] rounded-sm w-fit text-white">
        <span class="font-bold bg-black/25 -ml-1 -my-0.5 px-1.5 py-0.5 rounded-l-sm mr-1.5">{{ formatPercentage(offers_data?.o?.p) }} {{ ctrans("OFF") }}</span>
        {{ offers_data?.o?.l }}
        <FontAwesomeIcon :icon="faMoneyCheckEditAlt" class="text-white text-[0.8333rem] align-middle ml-1 mb-1" fixed-width aria-hidden="true" />
    </div>

    <!-- Label: First Order Bonus -->
    <div v-else-if="offers_data?.o?.st === 'fob'" class="bg-[#2a919e] text-white px-1 py-0.5 text-xs flex items-center rounded-sm w-fit">
        <span class="font-bold bg-black/25 -ml-1 -my-0.5 px-1.5 py-0.5 rounded-l-sm mr-1.5">{{ formatPercentage(offers_data?.o?.p) }} {{ ctrans("OFF") }}</span>
        {{ offers_data?.o?.l }}
    </div>


    <!-- Label: Step Discount -->
    <div v-else-if="offers_data?.o?.st === 'sd'" class="bg-[#A80000] px-1 py-0.5 text-xs border flex items-center border-[#A80000] rounded-sm w-fit text-white">
        <span class="font-bold bg-black/25 -ml-1 -my-0.5 px-1.5 py-0.5 rounded-l-sm mr-1.5">{{ formatPercentage(offers_data?.o?.p) }} {{ ctrans("OFF") }}</span>
        {{ offers_data?.o?.l }}
    </div>

    <!-- Label: Shop Offer -->
    <div v-else-if="isShopOffer" class="bg-[#A80000] px-1 py-0.5 text-xs border flex items-center border-[#A80000] rounded-sm w-fit text-white">
        <span class="font-bold bg-black/25 -ml-1 -my-0.5 px-1.5 py-0.5 rounded-l-sm mr-1.5">{{ formatPercentage(offers_data?.o?.p) }} {{ ctrans("OFF") }}</span>
        {{ offers_data?.o?.l }}
    </div>

    <!-- Label: Discretionary Discount (because sto is null) -->
    <div v-else-if="offers_data?.o?.sto === null && offers_data?.o?.st === null" class="bg-[#A80000] px-1 py-0.5 text-xs border flex items-center border-[#A80000] rounded-sm w-fit text-white" >
        <!-- <FontAwesomeIcon icon="fas fa-badge-percent" class="text-white text-[1.1667em] align-middle" fixed-width aria-hidden="true" /> -->
        <span class="font-bold bg-black/25 -ml-1 -my-0.5 px-1.5 py-0.5 rounded-l-sm mr-1.5">{{ formatPercentage(props.offers_data?.o?.p) }} {{ ctrans("OFF") }}</span>
        {{ offers_data?.o?.l }}
    </div>

    <div v-else class="flex items-center">
        <FontAwesomeIcon :icon="faMedal" class="text-[#E87928] text-[1rem] align-middle mr-1" fixed-width
            aria-hidden="true" />

        <div
            class="bg-[#E87928] px-1 py-0.5 border flex items-center border-[#E87928] rounded-sm w-fit text-white">
            <!-- Label: Gold Reward Amnesty -->
            <template v-if="offers_data?.o?.st === 'a'">
                <span class="ml-0.5 font-bold mr-1">
                    {{ formatPercentage(props.offers_data?.o?.p) }}
                </span>
                {{ ctrans("Gold Reward Amnesty") }}
            </template>

            <!-- Label: Gold Reward Member -->
            <template v-else-if="offers_data?.o?.st === 'i'">
                {{ ctrans("Gold Reward Member") }}
                <span class="ml-0.5 font-bold mr-1">
                    {{ formatPercentage(props.offers_data?.o?.p) }} OFF
                </span>
            </template>

            <!-- Label: default -->
            <template v-else>
                <span class="ml-0.5 font-bold mr-1">
                    {{ formatPercentage(props.offers_data?.o?.p) }}
                </span>
                {{ offers_data?.o?.l }}
            </template>
        </div>
    </div>
</template>