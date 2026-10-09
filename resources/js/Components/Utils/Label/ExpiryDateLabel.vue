<script setup lang="ts">
import { useFormatTime } from '@/Composables/useFormatTime'
import { ctrans } from '@/Composables/useTrans'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faViruses } from "@fad"
import { library } from "@fortawesome/fontawesome-svg-core"
library.add(faViruses)

const props = defineProps<{
    expiry_date?: Date | string
    batch_code?: string
}>()
</script>

<template>
    <span class="bg-slate-100 w-fit py-[1px] text-left">
        [
        <span v-if="props.expiry_date" v-tooltip="ctrans('Expired date')" class="text-right text-red-700">
            <FontAwesomeIcon icon="fad fa-viruses" class="text-red-500" fixed-width aria-hidden="true" />
            {{ useFormatTime(props.expiry_date) }}
        </span>
        <span v-if="props.expiry_date && props.batch_code" class="mx-1">-</span>
        <span v-if="props.batch_code" v-tooltip="ctrans('Batch code')" class="text-right">
            <span class="font-bold">{{ props.batch_code }}</span>
        </span>
        ]
    </span>
</template>