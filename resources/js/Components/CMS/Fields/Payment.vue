<script setup lang="ts">
import { ref } from 'vue';
import Button from '@/Components/Elements/Buttons/Button.vue';
import Popover from 'primevue/popover';
import { cloneDeep } from 'lodash-es';
import { library } from "@fortawesome/fontawesome-svg-core";
import { faShieldAlt, faTimes, faTrash } from "@fas";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faFacebookF, faInstagram, faTiktok, faPinterest, faYoutube, faLinkedinIn } from "@fortawesome/free-brands-svg-icons";

import btree from '@/../art/payment_service_providers/btree.svg'
import cash from '@/../art/payment_service_providers/cash.svg'
import checkout from '@/../art/payment_service_providers/checkout.svg'
import hokodo from '@/../art/payment_service_providers/hokodo.svg'
import pastpay from '@/../art/payment_service_providers/pastpay.svg'
import paypal from '@/../art/payment_service_providers/paypal.svg'
import sofort from '@/../art/payment_service_providers/sofort.svg'
import worldpay from '@/../art/payment_service_providers/worldpay.svg'
import xendit from '@/../art/payment_service_providers/xendit.svg'
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


library.add(faFacebookF, faInstagram, faTiktok, faPinterest, faYoutube, faLinkedinIn, faShieldAlt, faTimes, faTrash);

type PaymentItem = {
  name: string
  value: string
  image?: string
}

const props = withDefaults(defineProps<{
  modelValue?: PaymentItem[]
}>(), {
  modelValue: () => []
});

const emits = defineEmits<{ (e: 'update:modelValue', value: any[]): void }>();

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
  { name: 'Xendit', value: 'Xendit', image: xendit },
  { name: 'White Visa', value: 'White Visa', image: whiteVisa },
  { name: 'White Secure Payment', value: 'White Secure Payment', image: whiteSecurePayment },
  { name: 'White PayPal', value: 'White PayPal', image: whitePaypal },
  { name: 'White Mastercard', value: 'White Mastercard', image: whiteMastercard },
  { name: 'White Google Pay', value: 'White Google Pay', image: whiteGooglePay },
  { name: 'White Checkout', value: 'White Checkout', image: whiteCheckout },
  { name: 'White Apple Pay', value: 'White Apple Pay', image: whiteApplePay },
  { name: 'Apple Pay & Google Pay', value: 'Apple Pay & Google Pay', image: applepay_googlepay },
  { name: 'Mastercard & Visa', value: 'Mastercard & Visa', image: mastercard_visa },
]);


const _addop = ref<any>(null);
const _editop = ref<any[]>([]);

const addPayment = (value: { name: string, image: string }) => {
  const data = cloneDeep(props.modelValue);
  data.push({ ...value, value: value.name });
  emits('update:modelValue', data);
  _addop.value?.hide();
};

const updatePayment = (index: number, value: { name: string, image: string }) => {
  const data = cloneDeep(props.modelValue);
  data[index] = { ...value, value: value.name };
  emits('update:modelValue', data);
  _editop.value[index]?.hide();
};

const deletePayment = (event: Event, index: number) => {
  event.stopPropagation();
  const data = cloneDeep(props.modelValue);
  data.splice(index, 1);
  emits('update:modelValue', data);
};

const togglePopover = (event: Event, popoverRef: any) => {
  popoverRef?.toggle(event);
};
</script>

<template>
  <div>
    <div v-for="(item, index) in modelValue" :key="index" class="flex justify-center w-full mt-4">
      <div @click="(e) => togglePopover(e, _editop[index])"
           class="relative flex flex-col items-center border  bg-gray-200 border-gray-300 rounded-lg shadow-lg hover:shadow-xl transition-shadow duration-300 w-full p-4 m-2 transform hover:-translate-y-1">
        <button @click="(e) => deletePayment(e, index)"
                class="absolute top-2 right-2 text-xs p-1 focus:outline-none"
                aria-label="Delete">
          <FontAwesomeIcon :icon="['fas', 'times']" class="text-red-500 text-sm" />
        </button>
        <img v-if="item.image" class="h-20 w-20 object-contain" :src="item.image" alt="Payment" />
      </div>
      <Popover ref="_editop[index]">
        <div class="grid grid-cols-5 gap-4 p-4">
          <div v-for="icon in payments" :key="icon.value" @click="() => updatePayment(index, icon)"
               class="flex flex-col items-center border border-gray-200 rounded-lg shadow-md hover:shadow-lg transition-shadow p-3 cursor-pointer bg-gray-200">
            <img class="h-20 w-20 object-contain mb-2" :src="icon.image" alt="Payment">
            <div class="text-center text-sm font-medium truncate">{{ icon.name }}</div>
          </div>
        </div>
      </Popover>
    </div>
    
    <Button type="dashed" icon="fal fa-plus" label="Add Payments Method" full size="s" class="mt-2" @click="(e) => togglePopover(e, _addop)" />
    
    <Popover ref="_addop">
      <div class="grid grid-cols-5 gap-4 p-4">
        <div v-for="icon in payments" :key="icon.value" @click="() => addPayment(icon)"
             class="flex flex-col items-center border border-gray-200 rounded-lg shadow-md hover:shadow-lg transition-shadow p-3 cursor-pointer bg-gray-200">
          <img class="h-20 w-20 object-contain mb-2" :src="icon.image" alt="Payment">
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
