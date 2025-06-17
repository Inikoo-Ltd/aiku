<script setup lang="ts">
import { ref, onMounted } from 'vue';
import Button from '@/Components/Elements/Buttons/Button.vue';
import Popover from 'primevue/popover';
import { cloneDeep } from 'lodash-es';
import axios from 'axios';
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

const payments = ref<any[]>([]);
const _addop = ref<any>(null);
const _editop = ref<any[]>([]);

const selectImage = (code: string) => {
    if (!code) return null

    switch (code) {
        case 'btree':
            return btree
        case 'cash':
            return cash
        case 'checkout':
            return checkout
        case 'hokodo':
            return hokodo
        case 'accounts':
            return accounts
        case 'cond':
            return cond
        case 'bank':
            return bank
        case 'pastpay':
            return pastpay
        case 'paypal':
            return paypal
        case 'sofort':
            return sofort
        case 'worldpay':
            return worldpay
        case 'xendit':
            return xendit
        default:
            return null
    }
}


const GetPayment = async () => {
    try {
        const response = await axios.get(
            route('grp.org.accounting.org_payment_service_providers.index', { organisation: route().params['organisation'] })
        );
        
        if (response?.data?.data) {
            payments.value = response.data.data.map((item) => ({
                name: item.name,
                value: item.name,
                image: selectImage(item.code)
            }));
        } else {
            console.error('Invalid response format', response);
        }
    } catch (error) {
        console.error('Error fetching payments', error);
    }
};

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

onMounted(GetPayment);
</script>

<template>
  <div>
    <div v-for="(item, index) in modelValue" :key="index" class="flex justify-center w-full mt-4">
      <div @click="(e) => togglePopover(e, _editop[index])"
           class="relative flex flex-col items-center border border-gray-300 rounded-lg shadow-lg hover:shadow-xl transition-shadow duration-300 w-full p-4 m-2 transform hover:-translate-y-1">
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
               class="flex flex-col items-center border border-gray-200 rounded-lg shadow-md hover:shadow-lg transition-shadow p-3 cursor-pointer">
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
             class="flex flex-col items-center border border-gray-200 rounded-lg shadow-md hover:shadow-lg transition-shadow p-3 cursor-pointer">
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
