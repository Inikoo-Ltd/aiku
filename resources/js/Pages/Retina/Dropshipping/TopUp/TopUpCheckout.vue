<script setup lang="ts">
import { Head, usePage, router } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeading as TSPageHeading } from "@/types/PageHeading"
import { CheckoutComFlow } from "@/types/CheckoutComFlow"
import { FlashNotification as FlashNotificationType } from "@/types/FlashNotification"
import { onMounted, ref } from "vue"
import { PageProps as InertiaPageProps } from "@inertiajs/core"
import FlashNotification from "@/Components/UI/FlashNotification.vue"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"
import { loadCheckoutWebComponents } from "@checkout.com/checkout-web-components"
import { faSpinner } from "@fal"
import axios from "axios"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

const props = defineProps<{
    title: string,
    pageHead: TSPageHeading
    checkout_com_data: CheckoutComFlow
    top_up_payment_api_point_ulid: string
}>()

interface PagePropsWithFlash extends InertiaPageProps {
    flash: {
        notification?: FlashNotificationType
    }
}

const isLoading = ref(true)
const page = usePage<PagePropsWithFlash>()
console.log(props)
/* const { isLoading, initializeCheckout } = useCheckoutCom(props.checkout_com_data) */
/* onMounted(() => {
    initializeCheckout("flow-container")
}) */


const MAX_RETRIES = 5
const retryCount = ref(0)


const hitWebhookAfterSuccess = async (paymentResponseId: string) => {
    try {
        const response = await axios.post(
            route("retina.webhooks.checkout_com.top_up_payment_completed", {
                topUpPaymentApiPoint: props.top_up_payment_api_point_ulid
            }),
            {
                "cko-payment-id": paymentResponseId
            }
        )

        console.log("hitWebhookAfterSuccess:", response)

        const { status, msg } = response.data

        if (status === "success") {
            console.log("Payment successful:", response)
            retryCount.value = 0
            router.post(route("retina.redirect_success_paid_top_up", {
                creditTransaction: response.data.credit_transaction_id,
            }))

        } else if (status === "error") {
            console.warn("Payment error:", msg)
            // ✅ Show modal with a specific error message
            notify({
                title: trans("Something went wrong"),
                text: response.data.msg ? response.data.msg : trans("Failed to communicate with the payment service."),
                type: "error"
            })


        } else {
            console.log("Payment still processing:", status)

            if (retryCount.value < MAX_RETRIES) {
                retryCount.value++
                console.info(`Retrying... attempt ${retryCount.value} of ${MAX_RETRIES}`)
                setTimeout(() => hitWebhookAfterSuccess(paymentResponseId), 5000)
            } else {
                retryCount.value = 0
                // ⛔ Final error after max retries
                notify({
                    title: trans("Something went wrong"),
                    text: response.data.msg ? response.data.msg : trans("Failed to communicate with the payment service."),
                    type: "error"
                })
            }
        }
    } catch (error) {
        console.error("Checkout webhook failed:", error)
        retryCount.value = 0
        notify({
            title: trans("Something went wrong"),
            text: error.data.message ? error.data.message : trans("Failed to communicate with the payment service."),
            type: "error"
        })
    }
}

onMounted(async () => {
    // isLoading.value = true
    const checkout = await loadCheckoutWebComponents({
        paymentSession: props.checkout_com_data?.data,
        publicKey: props.checkout_com_data.public_key,
        environment: props.checkout_com_data.environment,
        locale: props.checkout_com_data.locale,
        onReady: () => {
            console.log("onReady")
        },

        onPaymentCompleted: (_component, paymentResponse) => {
            console.log("Create Payment with PaymentId: ", paymentResponse.id)
            hitWebhookAfterSuccess(paymentResponse.id)
        },

        onChange: (component) => {
            console.log(`onChange() -> isValid: "${component.isValid()}" for "${component.type}"`)
        },
        onError: (component, error) => {
            console.log("onError", error, "Component", component.type)
        },
        appearance: {
            colorAction: "rgb(15, 22, 38)"
        }
    })

    const flowComponent = checkout.create("flow")
    flowComponent.mount("#flow-container")
    setTimeout(() => {
        isLoading.value = false
    }, 2000)
})


</script>


<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <FlashNotification :notification="page.props.flash.notification" />
    <div class="flex justify-center">
        <div class="mt-6 relative w-full max-w-xl min-h-[200px]">
            <div id="flow-container" class="w-full border-b border-gray-300" />
            <div v-show="isLoading" class="pointer-events-none absolute top-0 h-full w-full z-10">
                <div class="w-full min-h-[200px] h-full xmd:h-[511px] skeleton">
                </div>
            </div>

            <Transition name="fade">
                <div v-if="retryCount > 0"
                    class="mt-4 px-4 py-2 rounded-lg bg-yellow-50 border border-yellow-300 text-yellow-800 text-sm font-medium flex items-center gap-2 animate-pulse">
                    <FontAwesomeIcon :icon="faSpinner" class="animate-spin" />
                    {{ trans("Retrying payment... Attempt :retryCount of :max_retries ", { retryCount: retryCount, max_retries: MAX_RETRIES }) }}
                </div>
            </Transition>

        </div>
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