<script setup lang="ts">
import { onMounted, ref, inject } from "vue"
import { router } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faSpinner, faExclamationTriangle } from "@fal"
import { faPaypal } from "@fortawesome/free-brands-svg-icons"
import axios from "axios"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"

const props = defineProps<{
    data: {
        label: string
        key: string
        environment: 'sandbox' | 'production'
        order_payment_api_point: string
        data: {
            client_token: string | null
            currency: string
        }
    }
    needToPay: number
    currency_code: string
    order: {
        id: number
        slug: string
    }
}>()

const locale = inject('locale', aikuLocaleStructure)
const layout = inject('layout', retinaLayoutStructure)

const currencyCode = props.data.data?.currency || props.currency_code || layout.iris?.currency?.code

const BRAINTREE_WEB_VERSION = '3.101.0'

const status = ref<'loading' | 'unavailable' | 'ready' | 'paying' | 'processing'>('loading')

const clientToken = ref<string | null>(props.data.data?.client_token || null)
const amountToPay = ref<number>(Number(props.needToPay))

const fetchClientToken = async () => {
    if (clientToken.value) {
        return
    }

    const response = await axios.get(
        route('retina.json.get_btree_client_token_to_pay_order', {
            order: props.order.id,
        })
    )

    if (response.data.status === 'success' && response.data.token) {
        clientToken.value = response.data.token
        amountToPay.value = Number(response.data.amount_to_pay)
    }
}

const loadScript = (src: string) => {
    return new Promise<void>((resolve, reject) => {
        if (document.querySelector(`script[src="${src}"]`)) {
            resolve()
            return
        }

        const script = document.createElement('script')
        script.src = src
        script.onload = () => resolve()
        script.onerror = () => reject(new Error(`Failed to load script: ${src}`))
        document.head.appendChild(script)
    })
}

const submitNonce = async (nonce: string) => {
    status.value = 'processing'

    try {
        const response = await axios.post(
            route('retina.models.place_order_pay_by_btree', {
                order: props.order.slug,
            }),
            {
                payment_method_nonce: nonce,
            }
        )

        if (response.data.status === 'ok') {
            router.post(route('retina.redirect_success_paid_order', {
                order: props.order.id,
            }))
        } else {
            notify({
                title: trans('Something went wrong'),
                text: response.data.message || trans('Failed to communicate with the payment service.'),
                type: 'error',
            })
            status.value = 'ready'
        }
    } catch (error) {
        console.error('Braintree PayPal submit nonce error:', error)
        notify({
            title: trans('Something went wrong'),
            text: error.response?.data?.message || error?.message || trans('Failed to communicate with the payment service.'),
            type: 'error',
        })
        status.value = 'ready'
    }
}

const initialisePaypalButton = async () => {
    try {
        await fetchClientToken()

        if (!clientToken.value) {
            status.value = 'unavailable'
            return
        }

        await loadScript(`https://js.braintreegateway.com/web/${BRAINTREE_WEB_VERSION}/js/client.min.js`)
        await loadScript(`https://js.braintreegateway.com/web/${BRAINTREE_WEB_VERSION}/js/paypal-checkout.min.js`)

        const braintree = (window as any).braintree

        const clientInstance = await braintree.client.create({
            authorization: clientToken.value,
        })

        const paypalCheckoutInstance = await braintree.paypalCheckout.create({
            client: clientInstance,
        })

        await paypalCheckoutInstance.loadPayPalSDK({
            currency: currencyCode,
            intent: 'capture',
            commit: true,
        })

        const paypal = (window as any).paypal

        await paypal.Buttons({
            fundingSource: paypal.FUNDING.PAYPAL,

            createOrder: () => {
                return paypalCheckoutInstance.createPayment({
                    flow: 'checkout',
                    amount: amountToPay.value.toFixed(2),
                    currency: currencyCode,
                })
            },

            onClick: () => {
                status.value = 'paying'
            },

            onApprove: async (data: { payerId: string, paymentId?: string }) => {
                const payload = await paypalCheckoutInstance.tokenizePayment(data)
                await submitNonce(payload.nonce)
            },

            onCancel: () => {
                status.value = 'ready'
            },

            onError: (error: Error) => {
                console.error('Braintree PayPal button error:', error)
                notify({
                    title: trans('Something went wrong'),
                    text: trans('There was an error processing your payment. Please try again or use a different payment method.'),
                    type: 'error',
                })
                status.value = 'ready'
            },
        }).render('#paypal-button')

        status.value = 'ready'
    } catch (error) {
        console.error('Braintree PayPal initialisation error:', error)
        status.value = 'unavailable'
    }
}

onMounted(() => {
    initialisePaypalButton()
})
</script>

<template>
    <div class="relative w-full max-w-xl mx-auto my-4 md:my-8 px-4">
        <div class="mb-2 pl-2">
            {{ trans("Need to pay") }}: <span class="font-bold">{{ locale.currencyFormat(currencyCode, amountToPay.toFixed(2)) }}</span>
        </div>

        <div class="relative min-h-[200px] border border-gray-300 rounded p-6">
            <div v-if="status === 'loading'" class="flex items-center gap-x-2 text-gray-600">
                <FontAwesomeIcon :icon="faSpinner" class="animate-spin" fixed-width aria-hidden="true" />
                {{ trans("Loading PayPal") }}
            </div>

            <div v-else-if="status === 'unavailable'" class="flex items-center gap-x-2 text-gray-500">
                <FontAwesomeIcon :icon="faExclamationTriangle" fixed-width aria-hidden="true" />
                {{ trans("PayPal is currently unavailable. Please use a different payment method.") }}
            </div>

            <div v-else-if="status === 'paying'" class="flex items-center gap-x-2 text-gray-600">
                <FontAwesomeIcon :icon="faPaypal" fixed-width aria-hidden="true" />
                {{ trans("Processing payment") }}
            </div>

            <div v-else-if="status === 'processing'" class="flex items-center gap-x-2 text-gray-600">
                <FontAwesomeIcon :icon="faSpinner" class="animate-spin" fixed-width aria-hidden="true" />
                {{ trans("Processing payment, please wait") }}
            </div>

            <div v-show="status === 'ready'" id="paypal-button" class="max-w-xs" />
        </div>
    </div>
</template>