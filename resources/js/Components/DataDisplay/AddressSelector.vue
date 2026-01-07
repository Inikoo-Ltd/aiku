<script setup lang='ts'>
import { ref } from 'vue'
import Modal from '@/Components/Utils/Modal.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faCircle } from '@fal'
import { faCircle as fasCircle } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
import Tag from '@/Components/Tag.vue'
library.add(faCircle, fasCircle)

const props = defineProps<{
    addressList: {
        id: number
        formatted_address: string
    }[]
}>()

const isModalAddress = ref(false)
const isEditAddress = ref(false)
const selectedEditableAddress = ref(false)
const isSelectAddressLoading = ref(false)
const onEditAddress = (address: {}) => {
    isEditAddress.value = true
    selectedEditableAddress.value = {...address}
}
const onSelectAddress = (selectedAddress) => {

    // router.patch(
    //     route(props.updateRoute.name, props.updateRoute.parameters),
    //     {
    //         delivery_address_id: selectedAddress.id
    //     },
    //     {
    //         onStart: () => isSelectAddressLoading.value = selectedAddress.id,
    //         onFinish: () => isSelectAddressLoading.value = false
    //     }
    // )
// props.boxStats.fulfilment_customer.address.value = selectedAddress
}

console.log('fff', props.addressList)


const xxxx = [
    {
  "id": 86347,
  "address_line_1": "51 Chester Road",
  "address_line_2": "",
  "sorting_code": "",
  "postal_code": "IG7 6AN",
  "locality": "Chigwell",
  "dependent_locality": "",
  "administrative_area": "",
  "country_code": "GB",
  "country_id": 48,
  "checksum": "3cc4c20e89d9e45782dcf260bfd2dcdb",
  "created_at": "2024-07-19T01:56:12.000000Z",
  "updated_at": "2024-07-19T01:56:12.000000Z",
  "country": {
    "code": "GB",
    "iso3": "GBR",
    "name": "United Kingdom"
  },
  tag: [
    'Invoice', 'Fixed'
  ],
  "formatted_address": "<p translate=\"no\">\n<span class=\"address-line1\">51 Chester Road</span><br>\n<span class=\"locality\">Chigwell</span><br>\n<span class=\"postal-code\">IG7 6AN</span><br>\n<span class=\"country\">United Kingdom</span>\n</p>"
},
{
  "id": 86457,
  "address_line_1": "51 Chester Road",
  "address_line_2": "",
  "sorting_code": "",
  "postal_code": "IG7 6AN",
  "locality": "Chigwell",
  "dependent_locality": "",
  "administrative_area": "",
  "country_code": "GB",
  "country_id": 48,
  "checksum": "3cc4c20e89d9e45782dcf260bfd2dcdb",
  "created_at": "2024-07-19T01:56:12.000000Z",
  "updated_at": "2024-07-19T01:56:12.000000Z",
  "country": {
    "code": "GB",
    "iso3": "GBR",
    "name": "United Kingdom"
  },
  tag: [
    'Pallet return'
  ],
  "formatted_address": "<p translate=\"no\">\n<span class=\"address-line1\">51 Chester Road</span><br>\n<span class=\"locality\">Chigwell</span><br>\n<span class=\"postal-code\">IG7 6AN</span><br>\n<span class=\"country\">United Kingdom</span>\n</p>"
}
]
</script>

<template>
    <div @click="isModalAddress = true" class="underline cursor-pointer">
        Select address
    </div>

    <Modal :isOpen="isModalAddress" @onClose="() => (isModalAddress = false)">
        <!-- Saved Address: list -->
        <template v-if="addressList.length">
            <div>
                <div class="text-center font-semibold mb-4">
                    Select address
                </div>

                <div class="grid grid-cols-2 gap-x-4">
                    <div v-for="(address, idxAddress) in addressList"
                        :key="idxAddress + address.id"
                        class="relative text-xs ring-1 ring-gray-300 rounded-lg px-5 py-3 h-fit transition-all cursor-pointer" :class="[
                            selectedEditableAddress?.id == address.id ? 'ring-2 ring-offset-4 ring-indigo-500' : ''
                        ]"
                        @click="selectedEditableAddress = address"
                    >
                        <div v-html="address.formatted_address"></div>
                        <div class="flex items-center gap-x-1 absolute bottom-2 right-2">
                            <Tag v-for="tag in address.tag" :label="tag" size="xxs"/>
                        </div>
                        <div class="flex items-center gap-x-1 absolute top-2 right-2">
                            <!-- <Button
                                            size="xxs"
                                            label="Edit"
                                            :key="address.id + '-' + selectedEditableAddress?.id"
                                            :type="selectedEditableAddress?.id == address.id ? 'primary' : 'tertiary'"
                                        /> -->
                            <!-- <div @click="() => onEditAddress(address)" class="inline cursor-pointer"
                                :class="[selectedEditableAddress?.id == address.id ? 'underline' : 'hover:underline']">
                                Edit
                            </div> -->
                            <!-- <Transition>
                                <div v-if="address.id"
                                    v-tooltip="'Selected as pallet return address'"
                                    class="bg-indigo-500/80 text-white cursor-default rounded-sm px-1.5 py-1 leading-none text-xxs">
                                    Selected
                                </div>
                                <Button v-else @click="() => onSelectAddress(address)"
                                    :label="isSelectAddressLoading == address.id ? '' : 'Select'" size="xxs"
                                    type="tertiary" :loading="isSelectAddressLoading == address.id" />
                            </Transition> -->
                            
                            <input v-model="selectedEditableAddress" type="radio" id="css" name="fav_language" :value="address" class="h-3.5 w-3.5 text-indigo-600">
                        </div>
                    </div>
                    
                    <div v-if="isEditAddress" class="relative bg-gray-100 p-4 rounded-md">
                        <div @click="() => (isEditAddress = false, selectedEditableAddress = null)"
                            class="absolute top-2 right-2 cursor-pointer">
                            <FontAwesomeIcon icon='fal fa-times' class='text-gray-400 hover:text-gray-500' fixed-width
                                aria-hidden='true' />
                        </div>
                        <!-- <PureAddress v-model="selectedEditableAddress"
                            :options="boxStats.fulfilment_customer.address.options"
                            @update:modelValue="() => boxStats.fulfilment_customer.address.value.id = null" /> -->
                        <div class="mt-6 flex justify-center">
                            <Button @click="() => onSubmitEditAddress()" label="Edit address"
                                :loading="isSubmitAddressLoading" full />
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <div v-else class="text-sm flex items-center justify-center h-3/4 font-medium text-center text-gray-400">
            No address found
        </div>
    </Modal>
</template>