<script setup lang='ts'>
import PureInput from '@/Components/Pure/PureInput.vue'
import InformationIcon from '@/Components/Utils/InformationIcon.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faTag } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faTag)

const props = defineProps<{
    offer: {
        code: string
        data_allowance_signature?: {
            percentage_off?: number
        }
        name?: string
    }
}>()
    
</script>

<template>
    <div>
        <PureInput
            :modelValue="offer.code + (offer.data_allowance_signature?.percentage_off ? ` - ${Number(offer.data_allowance_signature?.percentage_off) * 100}%` : '')"
            xonEnter="() => onApplyVoucher()"
            xdisabled="isLoadingVoucher || hasAttachedVoucher"
            class="!bg-green-100 font-bold !border !border-green-500"
            :prefix="{
                icon: 'fas fa-tag'
            }"
            :styleInput="{
                paddingTop: '5px',
                paddingBottom: '5px',
                xborder: '1px solid rgb(34 197 94 / var(--tw-border-opacity, 1))'
            }"
            classInput="!bg-transparent !text-green-700 "
        >
            <template #prefix>
                <div class="pl-3 -mr-2 whitespace-nowrap text-green-700">
                    <FontAwesomeIcon icon='fas fa-tag' class='' fixed-width aria-hidden='true' />
                </div>
            </template>
            <template v-if="offer?.name" #suffix>
                <div class="text-green-700 flex justify-center items-center px-2 absolute inset-y-0 right-0 gap-x-1 cursor-pointer">
                    <InformationIcon :information="offer?.name" />
                </div>
            </template>
        </PureInput>
    </div>
</template>