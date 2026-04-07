<script setup lang="ts">
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faBadgePercent } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { formatPercentage } from '@/Composables/Utils'
library.add(faBadgePercent)


const props = defineProps<{
    offers_data: {
        v: number
        o: {
            oc: number  // Offer Campaign id
            o: number  // Offer id
            oa: number  // Offer Allowance id
            t: string  // Type: "percentage"
            p: string  // Percentage: "10.0%"
            l: string  // Label
            st: string  // "a" => Gold Reward Amnesty, "i" => Gold Reward Member
        }
    }
}>()



</script>

<template>
    <div class="bg-green-500/20 px-1 py-0.5 text-xs border flex items-center border-green-500/50 rounded-sm w-fit text-green-700" >
        <template v-if="offers_data?.o?.st === 'a'">
            <FontAwesomeIcon icon="fas fa-candle-holder" class="text-green-500 text-[1em] align-middle" fixed-width aria-hidden="true" />
            <span class="ml-0.5 font-bold mr-1">{{ formatPercentage(props.offers_data?.o?.p)  }}</span>
            {{ ctrans("Gold Reward Amnesty") }}
        </template>
        <template v-else-if="offers_data?.o?.st === 'i'">
            <FontAwesomeIcon icon="fas fa-badge-percent" class="text-green-500 text-[1em] align-middle" fixed-width aria-hidden="true" />
            <span class="ml-0.5 font-bold mr-1">{{ formatPercentage(props.offers_data?.o?.p)  }}</span>
            {{ ctrans("Gold Reward Member") }}
        </template>
        <template v-else>
            <FontAwesomeIcon icon="fas fa-badge-percent" class="text-green-500 text-[1.1667em] align-middle" fixed-width aria-hidden="true" />
            <span class="ml-0.5 font-bold mr-1">{{ formatPercentage(props.offers_data?.o?.p)  }}</span>
            {{ offers_data?.o?.l }}
        </template>
    </div>
</template>