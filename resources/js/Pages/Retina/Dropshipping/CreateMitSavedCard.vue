<script setup lang="ts">
import { onMounted, ref } from "vue"
import { loadCheckoutWebComponents } from '@checkout.com/checkout-web-components';
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheckCircle } from "@fas"
import { faExclamationTriangle, faClock } from "@fad"
import { library } from "@fortawesome/fontawesome-svg-core"
library.add(faCheckCircle, faClock, faExclamationTriangle)

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

const notification = {
  "status": "success",
  "mit_saved_card": {
    "data": {
      "id": 20,
      "token": "****",
      "last_four_digits": "4242",
      "card_type": "Visa",
      "expires_at": "02/30",
      "processed_at": "2025-05-09T07:13:13.000000Z",
      "priority": 5,
      "state": "success",
      "label": null,
      "created_at": "2025-05-09T07:12:33.000000Z",
      "updated_at": "2025-05-09T07:13:13.000000Z"
    }
  }
}

interface Notification {
  status: string
  mit_saved_card: {
    data: {
      id: number
      token: string
      last_four_digits: string
      card_type: string
      expires_at: string
      processed_at: string
      priority: number
      state: string
      label: null | string
      created_at: string
      updated_at: string
    }
  }
}
const getDataWarning = (notif: Notification) => {
  if (notif.status === 'success') {
    return {
      message: trans('Success!'),
      bgColor: 'bg-green-200',
      textColor: 'text-green-600',
      icon: 'fas fa-check-circle',
      description: `Your ${notif.mit_saved_card.data.card_type} ending in ${notif.mit_saved_card.data.last_four_digits} has been saved successfully.`,
    }
  } else if (notif.status === 'error') {
    return {
      message: trans('Something went wrong'),
      bgColor: 'bg-red-200',
      textColor: 'text-red-600',
      icon: 'fad fa-exclamation-triangle',
      description: `Your ${notif.mit_saved_card.data.card_type} ending in ${notif.mit_saved_card.data.last_four_digits} is not successfully stored. Please contact administrator.`,
    }
  } else {
    return {
      message: trans('Pending'),
      bgColor: 'bg-gray-200',
      textColor: 'text-gray-600',
      icon: 'fad fa-clock',
      description: `We will review your ${notif.mit_saved_card.data.card_type} card ending in ${notif.mit_saved_card.data.last_four_digits}. It may take awhile to process.`,
    }
  }
}
</script>

<template>
    <Transition name="slide-to-right">
        <div v-if="$page.props.flash.notification" class="p-4">
        <div class="px-4 py-3 rounded-md"
            :class="getDataWarning($page.props.flash.notification).bgColor"
        >
            <div class="flex items-center" :class="getDataWarning($page.props.flash.notification).textColor">
                <div class="text-3xl">
                    <FontAwesomeIcon :icon="getDataWarning($page.props.flash.notification).icon" class="" fixed-width aria-hidden="true" />
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-bold">{{ getDataWarning($page.props.flash.notification).message }}</h3>
                    <div class="text-xs opacity-90 ">
                        <p>{{ getDataWarning($page.props.flash.notification).description }}</p>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </Transition>

    <div class="p-8 text-xl font-bold w-full text-center">
        <div class="relative w-full max-w-xl mx-auto my-8 overflow-hidden">
            <div v-show="!isLoading" id="flow-container" class="absolute " />
            <div class="w-full h-[450px]" :class="isLoading ? 'skeleton' : ''">

            </div>
        </div>
    </div>
</template>