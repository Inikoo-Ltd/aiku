<script setup lang="ts">
import { ref, computed } from 'vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import Popover from 'primevue/popover'
import { cloneDeep } from 'lodash-es'
import { library } from "@fortawesome/fontawesome-svg-core"
import { faShieldAlt, faTimes, faTrash } from "@fas"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faFacebookF, faInstagram, faTiktok, faPinterest, faYoutube, faLinkedinIn } from "@fortawesome/free-brands-svg-icons"

import btree from '@/../art/payment_service_providers/btree.svg'
import cash from '@/../art/payment_service_providers/cash.svg'
import checkout from '@/../art/payment_service_providers/checkout.svg'
import hokodo from '@/../art/payment_service_providers/hokodo.svg'
import pastpay from '@/../art/payment_service_providers/pastpay.svg'
import paypal from '@/../art/payment_service_providers/paypal.svg'
import sofort from '@/../art/payment_service_providers/sofort.svg'
import worldpay from '@/../art/payment_service_providers/worldpay.svg'
import bank from '@/../art/payment_service_providers/bank.svg'
import accounts from '@/../art/payment_service_providers/accounts.svg'
import cond from '@/../art/payment_service_providers/cond.svg'
import whiteVisa from '@/../art/payment_service_providers/white_visa.png'
import whiteSecurePayment from '@/../art/payment_service_providers/white_secure_payent.png'
import whitePaypal from '@/../art/payment_service_providers/white_paypal.png'
import whiteMastercard from '@/../art/payment_service_providers/white_mastercard.png'
import whiteGooglePay from '@/../art/payment_service_providers/white_google_pay.png'
import whiteCheckout from '@/../art/payment_service_providers/white_checkout.png'
import whiteApplePay from '@/../art/payment_service_providers/white_apple_pay.png'
import applepay_googlepay from '@/../art/payment_service_providers/googlepayapplepay.png'
import mastercard_visa from '@/../art/payment_service_providers/mastercardvisa.png'
import ApplePayFrame from '@/../art/payment_service_providers/ApplePayFrame.png'
import GooglePay from '@/../art/payment_service_providers/GooglePay.png'
import MasterCard from '@/../art/payment_service_providers/mastercard.png'
import Paypal from '@/../art/payment_service_providers/PayPal.png'
import SecureCheckout from '@/../art/payment_service_providers/SecureCheckout.png'
import VisaBrandMarlBlue from '@/../art/payment_service_providers/VisaBrandmarkBlueRGB2021.png'
import KarnaPaymentBadge from '@/../art/payment_service_providers/Klarna_Payment_Badge.png'
import KarnaLogo from '@/../art/payment_service_providers/Klarna-Logo.wine.png'
import KarnaPaylater from '@/../art/payment_service_providers/png-clipart-pay-later-with-klarna-logo-tech-companies.png'
import PastPayWhite from '@/../art/payment_service_providers/past_pay_white.png'
import GooglePayMark from '@/../art/payment_service_providers/google-pay-mark.png'
import BizumIconWhite from '@/../art/payment_service_providers/BizumIconWhite.png'
import BizumLogoWhite from '@/../art/payment_service_providers/BizumLogoWhite.png'
import BizumLogoColour from '@/../art/payment_service_providers/BizumLogoColour.png'
import BizumIconLogoColour from '@/../art/payment_service_providers/BizumIconLogoColour.png'
import BlinkLogoBW from '@/../art/payment_service_providers/BlinkLogoBW.png'
import BlinkLogoColour from '@/../art/payment_service_providers/BlinkLogoColour.png'
import BlinkPayLaterBW from '@/../art/payment_service_providers/BlinkPayLaterBW.png'
import BlinkPayLaterColour from '@/../art/payment_service_providers/BlinkPayLaterColour.png'
import P24 from '@/../art/payment_service_providers/P24.png'
import Przelewy24_logo from '@/../art/payment_service_providers/Przelewy24_logo.png'
import SwishLogoSecondaryLightBG from '@/../art/payment_service_providers/SwishLogoSecondaryLightBG.png'
import SwishLogoSecondaryGrayscaleLightBG from '@/../art/payment_service_providers/SwishLogoSecondaryGrayscaleLightBG.png'
import SwishLogoPrimaryPNG from '@/../art/payment_service_providers/SwishLogoPrimaryPNG.png'
import SwishLogoPrimaryGrayscaleLightBG from '@/../art/payment_service_providers/SwishLogoPrimaryGrayscaleLightBG.png'

library.add(
  faFacebookF, faInstagram, faTiktok, faPinterest,
  faYoutube, faLinkedinIn, faShieldAlt, faTimes, faTrash
)

type PaymentItem = {
  name: string
  value: string
  image?: string
}

const props = defineProps<{
  modelValue?: PaymentItem[] | null
}>()

const emits = defineEmits<{
  (e: 'update:modelValue', value: PaymentItem[]): void
}>()

/* SAFE ARRAY (handles null) */
const safeModelValue = computed<PaymentItem[]>(() => {
  return Array.isArray(props.modelValue) ? props.modelValue : []
})

const payments = ref<PaymentItem[]>([
  { name: 'Braintree', value: 'Braintree', image: btree },
  { name: 'Cash', value: 'Cash', image: cash },
  { name: 'Checkout', value: 'Checkout', image: checkout },
  { name: 'Hokodo', value: 'Hokodo', image: hokodo },
  { name: 'Accounts', value: 'Accounts', image: accounts },
  { name: 'Conditional Payment', value: 'Conditional Payment', image: cond },
  { name: 'Bank Transfer', value: 'Bank Transfer', image: bank },
  { name: 'PastPay', value: 'PastPay', image: pastpay },
  { name: 'PayPal', value: 'PayPal', image: paypal },
  { name: 'Sofort', value: 'Sofort', image: sofort },
  { name: 'Worldpay', value: 'Worldpay', image: worldpay },
  { name: 'White Visa', value: 'White Visa', image: whiteVisa },
  { name: 'White Secure Payment', value: 'White Secure Payment', image: whiteSecurePayment },
  { name: 'White PayPal', value: 'White PayPal', image: whitePaypal },
  { name: 'White Mastercard', value: 'White Mastercard', image: whiteMastercard },
  { name: 'White Google Pay', value: 'White Google Pay', image: whiteGooglePay },
  { name: 'White Checkout', value: 'White Checkout', image: whiteCheckout },
  { name: 'White Apple Pay', value: 'White Apple Pay', image: whiteApplePay },
  { name: 'Apple Pay & Google Pay', value: 'Apple Pay & Google Pay', image: applepay_googlepay },
  { name: 'Mastercard & Visa', value: 'Mastercard & Visa', image: mastercard_visa },
  { name: 'Apple Pay Frame', value: 'Apple Pay Frame', image: ApplePayFrame },
  { name: 'Google Pay', value: 'Google Pay', image: GooglePay },
  { name: 'MasterCard', value: 'MasterCard', image: MasterCard },
  { name: 'Paypal', value: 'Paypal', image: Paypal },
  { name: 'Secure Checkout', value: 'Secure Checkout', image: SecureCheckout },
  { name: 'Visa Brandmark Blue', value: 'Visa Brandmark Blue', image: VisaBrandMarlBlue },
  { name: 'Karna Badge', value: 'Karna Badge', image: KarnaPaymentBadge },
  { name: 'Karna', value: 'Karna', image: KarnaLogo },
  { name: 'Karna Paylater', value: 'Karna Paylater', image: KarnaPaylater },
  { name: 'PastPay white', value: 'PastPay white', image: PastPayWhite },
  { name: 'Google Pay Mark', value: 'Google Pay Mark', image: GooglePayMark },
  { name: 'Bizum Icon Logo Colour', value: 'Bizum Icon Logo Colour', image: BizumIconLogoColour },
  { name: 'Bizum Logo Colour', value: 'Bizum Logo Colour', image: BizumLogoColour },
  { name: 'Bizum Icon White', value: 'Bizum Icon White', image: BizumIconWhite },
  { name: 'Bizum Logo White', value: 'Bizum Logo White', image: BizumLogoWhite },
  { name: 'Blink Logo B&W', value: 'Blink Logo BW', image: BlinkLogoBW },
  { name: 'Blink Logo Colour', value: 'Blink Logo Colour', image: BlinkLogoColour },
  { name: 'Blink Pay Later B&W', value: 'Blink Pay Later BW', image: BlinkPayLaterBW },
  { name: 'Blink PayLater Colour', value: 'Blink PayLater Colour', image: BlinkPayLaterColour },
  { name: 'P24', value: 'P24', image: P24 },
  { name: 'Przelewy24 logo', value: 'Przelewy24 logo', image: Przelewy24_logo },
  { name: 'Swish Logo Secondary Light BG', value: 'Swish Logo Secondary Light BG', image: SwishLogoSecondaryLightBG },
  { name: 'Swish Logo Secondary Grayscale Light BG', value: 'Swish Logo Secondary Grayscale Light BG', image: SwishLogoSecondaryGrayscaleLightBG },
  { name: 'Swish Logo Primary PNG', value: 'Swish Logo Primary PNG', image: SwishLogoPrimaryPNG },
  { name: 'Swish Logo Primary Gray scale Light BG', value: 'Swish Logo Primary Grayscale Light BG', image: SwishLogoPrimaryGrayscaleLightBG },
])

const _addop = ref<any>(null);
const _editop = ref<any[]>([]);

const addPayment = (value: PaymentItem) => {
  const data = cloneDeep(safeModelValue.value)
  data.push({ ...value, value: value.name })
  emits('update:modelValue', data)
  _addop.value?.hide()
}


const updatePayment = (index: number, value: PaymentItem) => {
  const data = cloneDeep(safeModelValue.value)
  data[index] = { ...value, value: value.name }
  emits('update:modelValue', data)
  _editop.value[index]?.hide()
}


const deletePayment = (event: Event, index: number) => {
  event.stopPropagation()
  const data = cloneDeep(safeModelValue.value)
  data.splice(index, 1)
  emits('update:modelValue', data)
}

const togglePopover = (event: Event, popoverRef: any) => {
  popoverRef?.toggle(event)
}
</script>

<template>
  <div>
    <div v-for="(item, index) in safeModelValue" :key="index" class="flex justify-center w-full mt-4">
      <div
        @click="(e) => togglePopover(e, _editop[index])"
        class="relative flex flex-col items-center border bg-gray-200 border-gray-300 rounded-lg shadow-lg hover:shadow-xl transition-shadow duration-300 w-full p-4 m-2 transform hover:-translate-y-1"
      >
        <button
          @click="(e) => deletePayment(e, index)"
          class="absolute top-2 right-2 text-xs p-1 focus:outline-none"
        >
          <FontAwesomeIcon :icon="['fas','times']" class="text-red-500 text-sm" />
        </button>

        <img v-if="item.image" class="h-20 w-20 object-contain" :src="item.image" />
      </div>

      <Popover :ref="el => _editop[index] = el">
        <div class="grid grid-cols-5 gap-4 p-4">
          <div
            v-for="icon in payments"
            :key="icon.value"
            @click="() => updatePayment(index, icon)"
            class="flex flex-col items-center border border-gray-200 rounded-lg shadow-md hover:shadow-lg transition-shadow p-3 cursor-pointer bg-gray-200"
          >
            <img class="h-20 w-20 object-contain mb-2" :src="icon.image" />
            <div class="text-center text-sm font-medium truncate">{{ icon.name }}</div>
          </div>
        </div>
      </Popover>
    </div>

    <Button
      type="dashed"
      icon="fal fa-plus"
      label="Add Payments Method"
      full
      size="s"
      class="mt-2"
      @click="(e) => togglePopover(e, _addop)"
    />

    <Popover ref="_addop">
      <div class="grid grid-cols-5 gap-4 p-4">
        <div
          v-for="icon in payments"
          :key="icon.value"
          @click="() => addPayment(icon)"
          class="flex flex-col items-center border border-gray-200 rounded-lg shadow-md hover:shadow-lg transition-shadow p-3 cursor-pointer bg-gray-200"
        >
          <img class="h-20 w-20 object-contain mb-2" :src="icon.image" />
          <div class="text-center text-sm font-medium truncate">{{ icon.name }}</div>
        </div>
      </div>
    </Popover>
  </div>
</template>

<style scoped>
img {
  transition: transform 0.3s ease-in-out;
}
img:hover {
  transform: scale(1.1);
}
</style>