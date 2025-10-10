<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 10 May 2023 09:17:59 Malaysia Time, Pantai Lembeng, Bali, Id
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Multiselect from '@vueform/multiselect'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { Checkbox, ToggleButton, ToggleSwitch } from 'primevue'
import InformationIcon from '@/Components/Utils/InformationIcon.vue'
import { trans } from 'laravel-vue-i18n'
import { routeType } from '@/types/route'
import { notify } from '@kyvg/vue3-notification'

const props = defineProps<{
    form: any
    fieldName: string
    options: {
        same_as_contact: {
            label: string
            key_payload: string
            payload: number
        }
        countriesAddressData: any
    }
    fieldData: {
        placeholder: string
        required: boolean
    }
    updateRoute: routeType
}>()

console.log('props', props)


let addressValues = props.form[props.fieldName]?.address
const countries = {};

for (const item in props.options.countriesAddressData) {
    countries[item] = props.options.countriesAddressData[item]['label']
}

const administrativeAreas = (countryID: number) => props.options.countriesAddressData[countryID]['administrativeAreas']
const inAdministrativeAreas = (administrativeArea: string, countryID: number) => {
    !!props.options.countriesAddressData[countryID]['administrativeAreas'].find(c => c.name === administrativeArea);
}
const addressFields = (countryID: number) => {
    return props.options.countriesAddressData[countryID]['fields'];
}
const handleChange = () => props.form.clearErrors(props.fieldName);

const submitForm = () => {

    props.form
    .transform((data) => (
        data[props.fieldName].is_same_as_contact
        ? {
            [props.options.same_as_contact.key_payload]: props.options.same_as_contact.payload
        }
        : { delivery_address: data[props.fieldName].address }
    ))
    .post(route(props.updateRoute.name, props.updateRoute.parameters),
        {
            preserveScroll: true,
            onError: (e) => {
                notify({
                    title: trans("Something went wrong"),
                    text: e?.message || trans("Please try again later or contact administrator."),
                    type: "error",
                })
            }
        }
    )
}
</script>

<template>
    <div class="flex gap-x-2">
        <div class="w-full">
            <div class="flex items-center gap-x-3 pb-2 ">
                <div>
                    <label for="same_as_contact_address" class="block cursor-pointer">
                        {{ options.same_as_contact?.label }}
                        <InformationIcon :information="trans('If checked, the delivery address will be the same as the contact address')"/>
                    </label>
                </div>
                <ToggleSwitch 
                    v-model="props.form[props.fieldName].is_same_as_contact"
                    inputId="same_as_contact_address"
                    binary
                    name="same_as_contact_address"
                    size="small"
                />
            </div>
            
            <Transition name="slide-to-right">
                <div v-if="!props.form[props.fieldName].is_same_as_contact" class="grid gap-3 border-t pt-3 border-gray-300 ">
                    <!-- Country Options -->
                    <div class="">
                        <div class="relative">
                            <Multiselect
                                searchable
                                :options="countries"
                                v-model="addressValues['country_id']"
                                @update:model-value="handleChange"
                                :class="[
                                    form.errors[fieldName] || form.recentlySuccessful ? 'pr-8' : '',
                                    form.errors[fieldName] ? 'errorShake' : ''
                                ]"
                                :placeholder="props.fieldData.placeholder ?? 'Select a country'"
                                :canDeselect="false"
                                :canClear="false"
                                name="country_id"
                                id="country_id"
                            />
                            <div v-if="form.errors[fieldName] || form.recentlySuccessful"
                                class="absolute inset-y-2/4 right-0 pr-3 flex items-center pointer-events-none bg-red-500">
                                <FontAwesomeIcon icon="fas fa-exclamation-circle" v-if="form.errors[fieldName]"
                                    class="h-5 w-5 text-red-500" aria-hidden="true" />
                                <FontAwesomeIcon icon="fas fa-check-circle" v-if="form.recentlySuccessful"
                                    class="h-5 w-5 text-green-500" aria-hidden="true" />
                            </div>
                        </div>
                        <span v-if="form.errors[fieldName]" class="block mt-2 text-sm text-red-600">{{ form.errors[fieldName] }}</span>
                    </div>
                    <template v-if="addressValues['country_id']"
                        v-for="(addressFieldData, addressField) in addressFields(addressValues['country_id'])" :key="addressField">
                        <div class="grid ">
                            <div class="w-full ">
                                <div v-if="`${addressField}` === 'administrative_area'">
                                    <label for="administrative_area" class="block text-sm font-medium text-gray-700">
                                        {{ addressFieldData.label }}
                                    </label>
                                    <Multiselect
                                        v-if="administrativeAreas(addressValues['country_id']).length && (!addressValues['administrative_area'] || inAdministrativeAreas(addressValues['administrative_area'], addressValues['country_id']))"
                                        :options="administrativeAreas(addressValues['country_id'])" :label="'name'" :value-prop="'name'"
                                        v-model="addressValues['administrative_area']"
                                    />
                                    <input v-else v-model="addressValues['administrative_area']" type="text" name="administrative_area"
                                        id="administrative_area"
                                        class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" />
                                </div>
                                <div v-else>
                                    <label :for="`${addressField}`" class="block text-xs font-medium text-gray-700">
                                        {{ addressFieldData.label }}
                                        <span v-if="form.errors[addressField]" class="mt-2 text-sm text-red-600">{{ form.errors[addressField] }}</span>
                                    </label>
                                    <input @input="handleChange()" v-model="addressValues[addressField]" type="text"
                                        name="address_line_2" :id="`${addressField}`"
                                        class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" />
                                </div>
                            </div>
                            <div class="w-5 self-end">
                                <FontAwesomeIcon icon="fas fa-exclamation-circle" v-if="form.errors[addressField]"
                                    class="h-5 w-5 text-red-500" aria-hidden="true" />
                            </div>
                        </div>
                    </template>
                </div>
            </Transition>
        </div>

        
        <button @click="submitForm" class="h-9 align-bottom text-center" :disabled="form.processing || !form.isDirty" type="button">
            <template v-if="form.isDirty">
                <FontAwesomeIcon v-if="form.processing" icon='fad fa-spinner-third' class='text-2xl animate-spin' fixed-width aria-hidden='true' />
                <FontAwesomeIcon v-else icon="fad fa-save" class="h-8" :style="{ '--fa-secondary-color': 'rgb(0, 255, 4)' }" aria-hidden="true" />
            </template>
            <FontAwesomeIcon v-else icon="fal fa-save" class="h-8 text-gray-300" aria-hidden="true" />
        </button>
    </div>
</template>