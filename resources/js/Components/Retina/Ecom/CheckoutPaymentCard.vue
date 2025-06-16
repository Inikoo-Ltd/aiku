<script setup lang="ts">
import { onMounted, ref } from "vue"
import { loadCheckoutWebComponents } from '@checkout.com/checkout-web-components';
import { inject } from "vue"
import { useCopyText } from "@/Composables/useCopyText"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCopy } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
library.add(faCopy)

const props = defineProps<{
    data: {
        public_key: string
        environment: 'sandbox' | 'production'
        locale: string
        data: {
            payment_session_token: string
            payment_method: string
            payment_method_type: string
            payment_method_details: {
                card: {
                    brand: string
                    last4: string
                    exp_month: number
                    exp_year: number
                }
            }
        }
    }
    needToPay: number
    currency_code: string
}>()

const locale = inject('locale', {})
// console.log('dataa', props.data?.data?.payment_session_token)

const isLoading = ref(false)
onMounted(async () => {
    isLoading.value = true
    const checkout = await loadCheckoutWebComponents({
        paymentSession: props.data?.data,
        publicKey: props.data.public_key,
        environment: props.data.environment,
        locale: props.data.locale,
        onReady: () => {
            console.log("onReady")
        },
    
        onPaymentCompleted: (_component, paymentResponse) => {
            console.log("Create Payment with PaymentId: ", paymentResponse.id)
        },
    
        onChange: (component) => {
            console.log( `onChange() -> isValid: "${component.isValid()}" for "${component.type}"`, )
        },
        onError: (component, error) => {
            console.log("onError", error, "Component", component.type)
        },
    })
    
    const flowComponent = checkout.create('flow');
    flowComponent.mount('#flow-container');
    setTimeout(() => {
        isLoading.value = false
    }, 2000)
})

const isRecentlyCopied = ref(false)
const onClickCopy = (textToCopy: string) => {
    isRecentlyCopied.value = true
    useCopyText(textToCopy)
    setTimeout(() => {
        isRecentlyCopied.value = false
    }, 3000)
}
</script>

<template>
    <div class="relative w-full max-w-xl isolate mx-auto my-8 overflow-hidden">
        <div class="mb-2">
            Need to pay: <span class="font-bold">{{ locale.currencyFormat(currency_code, props.needToPay) }}</span>
            <Transition name="spin-to-right">
                <FontAwesomeIcon v-if="isRecentlyCopied" icon="fal fa-check" class="ml-1 text-green-500" fixed-width aria-hidden="true" />
                <FontAwesomeIcon v-else @click="() => onClickCopy(props.needToPay.toFixed(2))" icon="fal fa-copy" class="ml-1 text-gray-400 hover:text-gray-600 cursor-pointer" fixed-width aria-hidden="true" />
            </Transition>
        </div>

        <div xv-show="!isLoading" id="flow-container" class="absolute " />
        <div class="w-full h-[511px] -z-10" :class="isLoading ? 'skeleton' : ''">

        </div>
    </div>
</template>