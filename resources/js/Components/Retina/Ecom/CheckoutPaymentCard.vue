<script setup lang="ts">
import { onMounted, ref, inject } from "vue"
import { loadCheckoutWebComponents } from '@checkout.com/checkout-web-components';
import { useCopyText } from "@/Composables/useCopyText"
import { router } from "@inertiajs/vue3"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCopy, faSpinner } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import axios from "axios"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
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
        order_payment_api_point: string
    }
    needToPay: number
    currency_code: string
}>()

console.log('Checkout payment Card props', props.data)
const locale = inject('locale', {})

const isLoading = ref(true)
const isLoadingAfterSuccess = ref(false)
const MAX_RETRIES = 5;
const retryCount = ref(0);


const hitWebhookAfterSuccess = async (paymentResponseId: string) => {
  try {
    isLoadingAfterSuccess.value = true
    const response = await axios.post(
      route('retina.webhooks.checkout_com.order_payment_completed', {
        orderPaymentApiPoint: props.data.order_payment_api_point,
      }),
      {
        'cko-payment-id': paymentResponseId,
      }
    );

    console.log("hitWebhookAfterSuccess:", response);

    const { status, msg } = response.data;

    if (status === 'success') {
      console.log("Payment successful:", response);
      retryCount.value = 0
      router.post(route('retina.redirect_success_paid_order', {
        order: response.data.order_id,
      }));

    }else if (status === 'error') {
      console.warn("Payment error:", msg);
      // ✅ Show modal with specific error message
      notify({
              title: trans('Something went wrong'),
              text: response.data.msg ? response.data.msg : trans('Failed to communicate with the payment service.'),
              type: 'error',
          });


    } else {
      console.log("Payment still processing:", status);

      if (retryCount.value < MAX_RETRIES) {
        retryCount.value++;
        console.info(`Retrying... attempt ${retryCount.value} of ${MAX_RETRIES}`);
        setTimeout(() => hitWebhookAfterSuccess(paymentResponseId), 5000);
      } else {
        retryCount.value = 0
        // ⛔ Final error after max retries
          notify({
              title: trans('Something went wrong'),
              text: response.data.msg ? response.data.msg : trans('Failed to communicate with the payment service.'),
              type: 'error',
          });
      }
  }
  } catch (error) {
    console.error("Checkout webhook failed:", error);
    retryCount.value = 0
    notify({
      title: trans('Something went wrong'),
      text: error.data?.message || error?.message || trans('Failed to communicate with the payment service.'),
      type: 'error',
    });
  } finally {
    isLoadingAfterSuccess.value = false
  }
};

const paymentResponseId = ref<string | null>(null)
onMounted(async () => {
    // isLoading.value = true
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
            paymentResponseId.value = paymentResponse.id
            hitWebhookAfterSuccess(paymentResponse.id)
        },
    
        onChange: (component) => {
            console.log( `onChange() -> isValid: "${component.isValid()}" for "${component.type}"`, )
        },
        onError: (component, error) => {
            console.error("onError error:", error)
            console.error("onError component:", component)
        },
        appearance: {
            colorAction: 'rgb(15, 22, 38)',
        }
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
    <div class="relative w-full max-w-xl isolate mx-auto my-8 xoverflow-hidden">
        <div class="mb-2 pl-2">
            Need to pay: <span class="font-bold">{{ locale.currencyFormat(currency_code, props.needToPay) }}</span>
            <Transition name="spin-to-right">
                <FontAwesomeIcon v-if="isRecentlyCopied" icon="fal fa-check" class="ml-1 text-green-500" fixed-width
                    aria-hidden="true" />
                <FontAwesomeIcon v-else @click="() => onClickCopy(props.needToPay.toFixed(2))" icon="fal fa-copy"
                    class="ml-1 text-gray-400 hover:text-gray-600 cursor-pointer" fixed-width aria-hidden="true" />
            </Transition>
        </div>

        <!-- Section: Flow Container -->
        <div class="relative min-h-[200px]">
            <template v-if="paymentResponseId">
                <div xv-show="!isLoading" id="flow-container" class="xabsolute w-full xborder-b border-gray-300" />
                <div v-show="isLoading" class="pointer-events-none absolute top-0 h-full w-full z-10">
                    <div class="w-full min-h-[200px] h-full xmd:h-[511px] skeleton" xclass="isLoading ? 'skeleton' : ''">
                    </div>
                </div>
            </template>

            <div v-else class="h-64 flex flex-col items-center justify-center gap-y-2 bg-gray-100 border border-gray-300 rounded">
                <FontAwesomeIcon icon="fal fa-check-circle" class="text-3xl ml-1 text-green-500" fixed-width aria-hidden="true" />
                <div>{{ trans("Payment done. Waiting for confirmation...") }}</div>
            </div>

            <div v-show="isLoadingAfterSuccess" class="bg-black/70 text-white text-5xl flex justify-center items-center absolute top-0 h-full w-full z-10">
                <LoadingIcon />
            </div>
        </div>

        <Transition name="fade">
            <div v-if="retryCount > 0"
                class="absolute inset-0 xmt-4 px-4 py-2 rounded-lg bg-black/10 backdrop-blur-sm border border-black/50 text-yellow-600 text-lg font-medium flex items-center justify-center gap-2 xanimate-pulse">
                <FontAwesomeIcon :icon="faSpinner" class="animate-spin" />
                {{ trans("Retrying payment... Attempt :retryCount of :max_entries", { retryCount: retryCount, max_entries: MAX_RETRIES }) }}
            </div>
        </Transition>
    </div>
</template>

<style scoped>
    .fade-enter-active, .fade-leave-active {
  transition: opacity 0.3s ease;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
}
</style>