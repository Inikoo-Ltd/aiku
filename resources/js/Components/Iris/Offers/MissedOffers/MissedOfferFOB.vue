<script setup lang="ts">
import InformationIcon from '@/Components/Utils/InformationIcon.vue'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faExclamationCircle } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
library.add(faExclamationCircle)

const props = defineProps<{
    data: {
        label: string
    }
}>()

</script>

<template>
    <div class="bg-[#2a919e] text-white px-2 py-2 rounded-md mt-2 text-sm flex items-center justify-between gap-x-2">
        <!-- <InformationIcon :information="ctrans('First order bonus')" class="text-2xl" /> -->
        <span v-tooltip="ctrans('First order bonus')" class="text-2xl opacity-60 hover:opacity-100 cursor-pointer">
            <FontAwesomeIcon icon="fal fa-exclamation-circle" fixed-width aria-hidden="true" />
        </span>
        <div>
            <!-- Spend £100 + Vat to activate Your FIRST ORDER BONUS -->
            {{ data?.label }}
        </div>
    </div>
</template>