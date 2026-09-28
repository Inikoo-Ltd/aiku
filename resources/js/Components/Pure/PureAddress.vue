<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 10 May 2023 09:17:59 Malaysia Time, Pantai Lembeng, Bali, Id
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Multiselect from '@vueform/multiselect'
import "@vueform/multiselect/themes/default.css"
import { Address, AddressOptions } from "@/types/PureComponent/Address"
import { ctrans } from '@/Composables/useTrans'
import PureInput from '@/Components/Pure/PureInput.vue'
import { computed } from 'vue'
import { useCountriesAddressData } from '@/Composables/useCountriesAddressData'

const props = defineProps<{
    modelValue: Address
    options: AddressOptions
    fieldLabel?: boolean
}>()

const fetchedCountriesAddressData = props.options?.countriesAddressData ? null : useCountriesAddressData()

const countriesAddressData = computed(() => props.options?.countriesAddressData ?? fetchedCountriesAddressData?.value ?? {})

const countries = computed(() => {
    const labels = {}
    for (const item in countriesAddressData.value) {
        labels[item] = countriesAddressData.value[item]['label']
    }
    return labels
})

const administrativeAreas = (countryID: number) => countriesAddressData.value[countryID]?.administrativeAreas ?? []
const inAdministrativeAreas = (administrativeArea: string, countryID: number) => {
    return !!administrativeAreas(countryID).find(c => c.name === administrativeArea)
}

const addressFields = (countryID: number) => {
    return countriesAddressData.value[countryID]?.fields
}

</script>

<template>
    <div class="grid grid-cols-2 gap-3">
        
        <!-- Country Options -->
        <div v-if="fieldLabel" class="col-span-2">
            <label for="selectCountry" class="mb-1 block text-xs font-medium">
                {{ ctrans('Label') }}
            </label>
            <PureInput v-model="modelValue.label" placeholder="Enter address name" />
        </div>
        
        <!-- Country Options -->
        <div class="col-span-2">
            <label for="selectCountry" class="mb-1 block text-xs font-medium">
                {{ ctrans('Country') }}
            </label>
            <Multiselect v-model="modelValue.country_id" searchable :options="countries" placeholder="Select a country" :canDeselect="false" :canClear="false" />
        </div>

        <!-- Fields: City, Address, Addres Line 2 -->
        <template v-if="modelValue.country_id" v-for="(addressFieldData, addressField) in addressFields(modelValue.country_id)" :key="addressField">
            <div class="grid col-span-2">
                <div class="w-full ">
                    <div v-if="`${addressField}` === 'administrative_area'">
                        <label for="administrative_area" class="block text-sm font-medium">
                            {{ addressFieldData.label }}
                        </label>

                        <Multiselect
                            v-if="administrativeAreas(modelValue.country_id).length && (!modelValue.administrative_area || inAdministrativeAreas(modelValue.administrative_area, modelValue.country_id))"
                            v-model="modelValue.administrative_area"
                            :options="administrativeAreas(modelValue.country_id)" label="name" valueProp="name"
                            :placeholder="`Select ${addressFieldData.label}`"
                        />
                        <input v-else
                            v-model="modelValue.administrative_area"
                            type="text"
                            name="administrative_area"
                            id="administrative_area"
                            autocomplete="password"
                            class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" />
                    </div>
                    
                    <div v-else>
                        <label :for="`${addressField}`" class="block text-xs font-medium">
                            {{ addressFieldData.label }}
                        </label>

                        <input v-model="modelValue[addressField]"
                            type="text"
                            :name="addressField"
                            :id="addressField"
                            autocomplete="password"
                            :placeholder="`Enter ${addressFieldData.label}`"
                            class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 placeholder:text-gray-400 rounded-md" />
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>