<script setup lang="ts">
import { onMounted, ref } from "vue"
import { loadCheckoutWebComponents } from '@checkout.com/checkout-web-components';

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
}>()

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
    isLoading.value = false
})
</script>

<template>
    <div class="relative w-full max-w-xl mx-auto my-8 overflow-hidden">
        <div v-show="!isLoading" id="flow-container" class="absolute " />
        <div class="w-full h-[450px]" :class="isLoading ? 'skeleton' : ''">

        </div>
    </div>
</template>