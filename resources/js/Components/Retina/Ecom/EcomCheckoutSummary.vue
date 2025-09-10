<script setup lang="ts">
    
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faClipboard, faDollarSign, faPencil } from "@fal"
import OrderSummary from "@/Components/Summary/OrderSummary.vue"
import { trans } from "laravel-vue-i18n"
import { inject, ref } from "vue"
import { AddressManagement } from "@/types/PureComponent/Address"
import Modal from "@/Components/Utils/Modal.vue"
import AddressEditModal from "@/Components/Utils/AddressEditModal.vue"

const props = defineProps<{
    summary: {
        net_amount: string
        gross_amount: string
        tax_amount: string
        goods_amount: string
        services_amount: string
        charges_amount: string
    }
    balance?: string
    address_management?: AddressManagement
}>()

const locale = inject('locale', {})

const isModalShippingAddress = ref(false)

</script>

<template>
    <div class="py-4 grid grid-cols-3 px-4 ">
        <!-- Section: Billing Address -->
        <div class="">
            <div class="font-semibold">
                <FontAwesomeIcon :icon="faDollarSign" class="" fixed-width aria-hidden="true" />
                {{ trans("Billing Address") }}
            </div>
            <div v-if="summary?.customer?.addresses?.billing?.formatted_address" class="pl-6 pr-3" v-html="summary?.customer?.addresses?.billing?.formatted_address">
        
            </div>
            <div v-else class="text-gray-400 italic pl-6 pr-3">
                {{ trans("No billing address") }}
            </div>
        </div>
        
        <!-- Section: Delivery Address -->
        <div class="">
            <div class="font-semibold">
                <FontAwesomeIcon :icon="faClipboard" class="" fixed-width aria-hidden="true" />
                {{ trans("Delivery Address") }}
            </div>

            <div v-if="summary?.customer?.addresses?.delivery?.formatted_address" class="pl-6 pr-3" v-html="summary?.customer?.addresses?.delivery?.formatted_address">
            </div>

            <div v-else class="text-gray-400 italic pl-6 pr-3">
                {{ trans("No delivery address") }}
            </div>

            <div v-if="address_management?.address_update_route" @click="isModalShippingAddress = true"
                class="pl-6 pr-3 w-fit underline cursor-pointer hover:text-gray-700">
                {{ trans("Edit") }}
                <FontAwesomeIcon icon="fal fa-pencil" class="" fixed-width aria-hidden="true"/>
            </div>
        </div>

        <!-- Section: balance, charges, shipping, tax -->
        <div>
            <div class="border-b border-gray-200 pb-0.5 flex justify-between pl-1.5 pr-4 mb-1.5">
                <div class="">{{ trans("Current balance") }}:</div>
                <div>
                    {{ locale.currencyFormat(summary.order_summary?.currency?.data?.code, balance ?? 0) }}
                </div>
            </div>
            
            <div class="border border-gray-200 p-2 rounded">
                <OrderSummary
                    :order_summary="summary.order_summary"
                    :currency_code="summary.order_summary?.currency?.data?.code"
                />
            </div>
        </div>

        <!-- Section: Edit Delivery address -->
        <Modal v-if="address_management"
            :isOpen="isModalShippingAddress"
            @onClose="() => (isModalShippingAddress = false)"
            width="w-full max-w-lg"
            closeButton
        >
            <AddressEditModal
                :addresses="address_management.addresses"
                :address="summary?.customer?.addresses?.delivery"
                :updateRoute="address_management.address_update_route"
                @submitted="() => (isModalShippingAddress = false)"
                closeButton
            />
        </Modal>
    </div>
</template>

<style lang="scss">

@property --a { /* must register --a to animate it */
	syntax: '<angle>';
	initial-value: 0deg;
	/* used only on pseudo, nowhere to be inherited, 
	 * better perf if set false, see 
	 * https://www.bram.us/2024/10/03/benchmarking-the-performance-of-css-property/ */
	inherits: false
}

.vvvvvv {
	/* hide outer part of glow */
	overflow: hidden;
	/* needed for absolutely positioned pseudo */
	position: relative;
	/* adjust width as needed IF it's even necessary to set */
	width: Min(12.5em, 80vmin);
	/* adjust aspect-ratio OR height IF height not given by content */
	aspect-ratio: 1;
	/* round outer card corners */
	border-radius: .5em;
	
	/* text & layout styles below just for prettifying */
	place-self: center;
	place-content: center;
	padding: .5em;
	color: #ededed;
	font: clamp(1em, 2vw + 2vh, 2em) sans-serif;
	text-align: center;
	text-transform: uppercase;
	text-wrap: balance
}

.vvvvvv::before {
	/* grid doesn't work for stacking when a stacked item is text node */
	position: absolute;
	/* place behind card content, so card text is selectable, etc */
	z-index: -1;
	/* best if inset is at least half the border-width with minus */
	inset: -1em;
	/* reserve space for border */
	border: solid 1.25em;
	border-image: 
		/* adjust gradient as needed, I just used a random palette */
		conic-gradient(from var(--a), #669900, #99cc33, #ccee66, 
				#006699, #3399cc, #990066, #cc3399, 
				#ff6600, #ff9900, #ffcc00, #669900) 1;
	/* blur this pseudo */
	filter: blur(.75em);
	/* tweak animation duration as necessary */
	animation: a 4s linear infinite;
	/* needed so pseudo is displayed */
	content: ''
}

/* animate --a from its initial-value 0deg to 1turn */
@keyframes a { to { --a: 1turn } }



body {
	background: /* just to illustrate card transparency */
		url(https://images.unsplash.com/photo-1729824346255-52a8f898fe84?w=1400) 
			50%/ cover #212121;
	/* darken image (multiplying its RGB channels with 
	 * those of background-color) for better text contrast */
	background-blend-mode: multiply

}</style>