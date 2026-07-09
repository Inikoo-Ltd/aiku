<script setup lang='ts'>
import PureInput from '@/Components/Pure/PureInput.vue'
import InformationIcon from '@/Components/Utils/InformationIcon.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faTag } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
import { computed } from 'vue'
import { ctrans } from '@/Composables/useTrans'
library.add(faTag)

const props = defineProps<{
    offer: {
        code: string
        data_allowance_signature?: {
            percentage_off?: number
        }
        name?: string
        allowances?: Array<{
            type?: string
            label?: string
            percentage_off?: number | string
            product_code?: string | null
            product_name?: string | null
            quantity?: number
            min_order_amount?: number | null
        }>
    }
}>()

const voucherLabel = computed(() => {
    const allowance = props.offer.allowances?.[0]

    let detail = ''
    if (allowance?.type === 'gift') {
        detail = `${ctrans('Free gift')} (${allowance.product_code ?? ctrans('unknown')})`
    } else if (allowance?.type === 'shipping') {
        detail = ctrans('Discounted shipping')
    } else if (props.offer.data_allowance_signature?.percentage_off) {
        detail = `${Number(props.offer.data_allowance_signature.percentage_off) * 100}%`
    }

    return detail ? `${props.offer.code} - ${detail}` : props.offer.code
})
</script>

<template>
    <div>
        <PureInput
            :modelValue="voucherLabel"
            xonEnter="() => onApplyVoucher()"
            xdisabled="isLoadingVoucher || hasAttachedVoucher"
            disabled
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