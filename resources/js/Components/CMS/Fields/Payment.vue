<script setup lang="ts">
import { ref, onMounted } from 'vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import PureMultiselect from '@/Components/Pure/PureMultiselect.vue'
import { cloneDeep } from 'lodash-es'
import axios from 'axios'
import Popover from 'primevue/popover';

import { library } from "@fortawesome/fontawesome-svg-core"
import { faShieldAlt, faTimes, faTrash } from "@fas"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faFacebookF, faInstagram, faTiktok, faPinterest, faYoutube, faLinkedinIn } from "@fortawesome/free-brands-svg-icons";

library.add(faFacebookF, faInstagram, faTiktok, faPinterest, faYoutube, faLinkedinIn, faShieldAlt, faTimes, faTrash)

const props = defineProps<{
    modelValue: any,
}>();

const emits = defineEmits<{
    (e: 'update:modelValue', value: {}): void
}>();

const payments = ref([])
const _addop = ref(null);
const _editop = ref(null);

const GetPayment = async () => {
    try {
        const response = await axios.get(
            route('grp.org.accounting.org-payment-service-providers.index', { organisation: route().params['organisation'] }),
        )
        if (response && response.data && response.data.data) {
            const results = response.data.data.map((item) => ({
                name: item.name,
                value: item.name,
                image: item.logo
            }))
            payments.value = results
        } else {
            console.error('Invalid response format', response)
        }
    } catch (error: any) {
        console.error('error', error)
    }
}

const addPayments = (value: { name: string, image: string }) => {
    const data = cloneDeep(props.modelValue);
    data.push({ name: value.name, value: value.name, image: value.image });
    emits('update:modelValue', data);
    _addop.value.hide();
};

const updatePayment = (index: number, value: { name: string, image: string, value : string }) => {
    const data = cloneDeep(props.modelValue);
    data[index] = { name: value.name, value: value.name, image: value.image };
    emits('update:modelValue', data);
    _editop.value[index].hide();
};

const deleteSocial = (event: Event, index: number) => {
    event.stopPropagation();
    event.preventDefault();
    const data = cloneDeep(props.modelValue);
    data.splice(index, 1);
    emits('update:modelValue', data);
};

const toggleAdd = (event: Event) => {
    _addop.value?.toggle(event);
};

const toggleEdit = (event: Event, index : Number) => {
    console.log(_editop.value)
    _editop.value[index].toggle(event);
};

onMounted(() => {
    GetPayment()
});

</script>

<template>
  <div>
        <div v-for="(item, index) in modelValue" :key="index" class="flex justify-center w-full mt-4">
            <div @click="(e)=>toggleEdit(e,index)" :style="{ backgroundColor: props.background || '#f9f9f9' }"
                class="relative flex flex-col items-center border border-gray-300 rounded-lg shadow-lg hover:shadow-xl transition-shadow duration-300 w-full p-4 m-2 transform hover:-translate-y-1">
                <!-- Delete Button -->
                <button @click="(e) => deleteSocial(e, index)"
                    class="absolute top-2 right-2  text-xs  p-1 focus:outline-none"
                    aria-label="Delete">
                    <FontAwesomeIcon :icon="['fas', 'times']" class="text-red-500 text-sm" />
                </button>

                <img v-if="item.image" class="h-auto max-h-7 md:max-h-8 max-w-full w-fit" :src="item.image"
                    alt="avatar" />
            </div>
            <Popover ref="_editop">
                <div class="grid grid-cols-5 gap-6">
                    <div v-for="icon in payments" :key="icon.value" class="pr-4">
                        <div @click="() => updatePayment(index,icon)" :style="{ backgroundColor: props.background }"
                            class="flex flex-col items-center border-2 border-gray-200 rounded-lg shadow-md hover:shadow-lg transition-shadow w-full p-4 m-2">
                            <img class="w-12 h-12 rounded-full object-contain object-center mb-2 group-hover:opacity-75"
                                :src="icon.image" alt="avatar">
                            <div class="text-center truncate" style="max-width: 150px;">
                                {{ icon.name }}
                            </div>
                        </div>
                    </div>
                </div>
            </Popover>
            </div>
        <Button type="dashed" icon="fal fa-plus" label="Add Payments Method" full size="s" class="mt-2" @click="toggleAdd" />
        
        <Popover ref="_addop">
            <div class="grid grid-cols-5 gap-6">
                <div v-for="icon in payments" :key="icon.value" class="pr-4">
                    {{ icon }}
                    <div @click="() => addPayments(icon)" :style="{ backgroundColor: props.background }"
                        class="flex flex-col items-center border-2 border-gray-200 rounded-lg shadow-md hover:shadow-lg transition-shadow w-full p-4 m-2">
                        <img class="w-12 h-12 rounded-full object-contain object-center mb-2 group-hover:opacity-75"
                            :src="icon.image" alt="avatar">
                        <div class="text-center truncate text-white" style="max-width: 150px;">
                            {{ icon.name }}
                        </div>
                    </div>
                </div>
            </div>
        </Popover>
    </div>
</template>

<style scoped></style>
