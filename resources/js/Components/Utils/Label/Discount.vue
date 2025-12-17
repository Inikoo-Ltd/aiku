<script setup lang="ts">
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faBadgePercent } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
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
        }
    }
}>()
</script>

<template>
    <div class="bg-green-500/20 px-1 py-0.5 text-xs border flex items-center border-green-500/50 rounded-sm w-fit text-green-700" >
        <FontAwesomeIcon icon="fas fa-badge-percent" class="text-green-500 text-sm align-middle" fixed-width aria-hidden="true" />
        <span class="ml-0.5 font-bold mr-1">{{ offers_data?.o?.p }}</span> {{ offers_data?.o?.l }}
    </div>
</template>