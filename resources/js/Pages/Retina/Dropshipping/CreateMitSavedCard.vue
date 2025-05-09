<script setup lang="ts">
import { onMounted, ref } from "vue"
import { loadCheckoutWebComponents } from '@checkout.com/checkout-web-components';

interface CheckoutComData {
  label: string;
  key: string;
  public_key: string;
  environment: "production" | "sandbox";
  locale: string;
  icon: string;
  data: any; // The payment session data from Checkout.com
}

const props = defineProps<{
  checkout_com_data: CheckoutComData
}>()

const isLoading = ref(false)
onMounted(async () => {
    isLoading.value = true
    const checkout = await loadCheckoutWebComponents({
        paymentSession: props.checkout_com_data.data,
        publicKey: props.checkout_com_data.public_key,
        environment: props.checkout_com_data.environment,
        locale: props.checkout_com_data.locale,
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

    <div class="p-8 text-xl font-bold w-full text-center">
        <div class="relative w-full max-w-xl mx-auto my-8 overflow-hidden">
            <div v-show="!isLoading" id="flow-container" class="absolute " />
            <div class="w-full h-[450px]" :class="isLoading ? 'skeleton' : ''">

            </div>
        </div>
    </div>
</template>