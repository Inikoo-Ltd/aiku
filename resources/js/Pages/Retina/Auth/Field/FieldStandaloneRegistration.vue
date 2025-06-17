<script setup lang="ts">
import { trans } from 'laravel-vue-i18n'
import { computed, inject, ref } from 'vue'

import Address from "@/Components/Forms/Fields/Address.vue"
import Textarea from "primevue/textarea"
import Select from "primevue/select"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faBuilding, faGlobe, faPhone, faUser, faInfoCircle } from "@fal"
import { faExclamationCircle } from "@fas"
import { IconField, InputIcon, InputText } from 'primevue'
import { library } from "@fortawesome/fontawesome-svg-core"
import CustomerDataForm from '@/Components/CustomerDataForm.vue'
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
library.add(faExclamationCircle)

const props = defineProps<{
    countriesAddressData: {}
    polls: []
    form: {}
}>()

const layout = inject('layout', retinaLayoutStructure)
console.log('layout', layout.retina.type)

const addressFieldData = {
    type: "address",
    label: "Address",
    value: {
        address_line_1: null,
        address_line_2: null,
        sorting_code: null,
        postal_code: null,
        locality: null,
        dependent_locality: null,
        administrative_area: null,
        country_code: null,
        country_id: 48,
    },
    options: props.countriesAddressData,
}


const simplePolls = computed(() =>
    props.polls.map(({ name, label, options }) => ({ name, label, options }))
)

const initialPolls: Record<string, string | null> = {}
simplePolls.value.forEach((poll) => {
    initialPolls[poll.name] = poll.options.length > 1 ? null : ""
})

const interestsList = ref([
    { label: 'Pallets Storage', value: 'pallets_storage' },
    { label: 'Dropshipping', value: 'dropshipping' },
    { label: 'Space (Parking)', value: 'rental_space' },
]);

const toggleInterest = (interestValue: string) => {
    const index = props.form.interest.indexOf(interestValue);
    if (index > -1) {
        props.form.interest.splice(index, 1);
    } else {
        props.form.interest.push(interestValue);
    }
};
</script>

<template>
    
    <!-- First Name -->
    <div class="sm:col-span-3">
        <label
            for="name"
            class="capitalize block text-sm font-medium text-gray-700"
        >
            {{ trans("Name") }}
        </label>

        <div class="mt-2">
            <IconField>
                <InputIcon>
                    <FontAwesomeIcon :icon="faUser" />
                </InputIcon>
                <InputText
                    v-model="form.contact_name"
                    id="contact_name"
                    name="contact_name"
                    class="w-full"
                    required />
            </IconField>
            <p
                v-if="form.errors.contact_name"
                class="text-sm text-red-600 mt-1">
                {{ form.errors.contact_name }}
            </p>
        </div>
    </div>

    <!-- Field: Phone Number -->
    <div class="sm:col-span-3">
        <label
            for="phone-number"
            class="capitalize block text-sm font-medium text-gray-700"
        >
            <FontAwesomeIcon icon="fas fa-asterisk" class="text-red-500 text-xxs" fixed-width aria-hidden="true" />
            {{ trans("Phone Number") }}
        </label>
        <div class="mt-2">
            <IconField class="w-full" :class="form.errors.phone ? 'errorShake' : ''">
                <InputIcon>
                    <FontAwesomeIcon :icon="faPhone" />
                </InputIcon>
                <InputText
                    v-model="form.phone"
                    @update:model-value="(e) => form.clearErrors('phone')"
                    type="text"
                    id="phone-number"
                    name="phone"
                    class="w-full"
                    required />
            </IconField>
            <p v-if="form.errors.phone" class="text-sm text-red-600 mt-1">
                {{ form.errors.phone }}
            </p>
        </div>
    </div>

    <!-- Field: Business Name -->
    <div class="sm:col-span-6">
        <label
            for="business-name"
            class="capitalize block text-sm font-medium text-gray-700"
        >
            <FontAwesomeIcon icon="fas fa-asterisk" class="text-red-500 text-xxs" fixed-width aria-hidden="true" />
            {{ trans("Business Name") }}
        </label>
        <div class="mt-2">
            <IconField class="w-full" :class="form.errors.company_name ? 'errorShake' : ''">
                <InputIcon>
                    <FontAwesomeIcon :icon="faBuilding" />
                </InputIcon>
                <InputText
                    v-model="form.company_name"
                    @update:model-value="(e) => form.clearErrors('company_name')"
                    type="text"
                    id="business-name"
                    name="company_name"
                    class="w-full" />
            </IconField>
            <p
                v-if="form.errors.company_name"
                class="text-sm text-red-600 mt-1">
                {{ form.errors.company_name }}
            </p>
        </div>
    </div>

    <!-- Field: Website -->
    <div class="sm:col-span-6">
        <label
            for="website"
            class="capitalize block text-sm font-medium text-gray-700"
            >{{ trans("Website") }}</label
        >
        <div class="mt-2">
            <IconField class="w-full">
                <InputIcon>
                    <FontAwesomeIcon :icon="faGlobe" />
                </InputIcon>
                <InputText v-model="form.website" class="w-full" />
            </IconField>
            <p v-if="form.errors.website" class="text-sm text-red-600 mt-1">
                {{ form.errors.website }}
            </p>
        </div>
    </div>

    <div class="sm:col-span-6">
        <hr />
    </div>

    <!-- Field: Country -->
    <div class="sm:col-span-6">
        <label for="address" class="capitalize block text-sm font-medium text-gray-700" >
            <FontAwesomeIcon icon="fas fa-asterisk" class="text-red-500 text-xxs" fixed-width aria-hidden="true" />
            {{ trans("Country") }}
        </label >
        <Address
            v-model="form[contact_address]"
            fieldName="contact_address"
            :form="form"
            :options="{ countriesAddressData: countriesAddressData }"
            :fieldData="addressFieldData" />
    </div>

    <div class="sm:col-span-6">
        <hr />
    </div>

    <!-- Field: Polls -->
    <div
        v-for="(pollReply, idx) in form.poll_replies"
        :key="pollReply.id"
        class="sm:col-span-6">
        <div class="block text-sm font-medium text-gray-700">
            <FontAwesomeIcon v-if="pollReply.is_required" icon="fas fa-asterisk" class="text-red-500 text-xxs" fixed-width aria-hidden="true" />
            {{ pollReply.label }}
        </div>
        
        <div class="mt-2" :class="form.errors?.[`poll_replies.${idx}`] ? 'errorShake' : ''">
            <Select
                v-if="pollReply.type === 'option'"
                v-model="form.poll_replies[idx].answer"
                @update:model-value="(e) => form.clearErrors(`poll_replies.${idx}`)"
                :options="props.polls[idx].options"
                optionLabel="label"
                optionValue="id"
                :placeholder="`Please Choose One`"
                class="w-full" />
            <Textarea
                v-else
                v-model="form.poll_replies[idx].answer"
                @update:model-value="(e) => form.clearErrors(`poll_replies.${idx}`)"
                rows="5"
                cols="30"
                placeholder="Your answer…"
                class="w-full border rounded-md p-2" />
        </div>
        <p v-if="form.errors[`poll_replies.${idx}`]" class="mt-1 text-sm text-red-600">
            *{{ form.errors[`poll_replies.${idx}`] }}
        </p>
    </div>

    <template v-if="layout.retina.type === 'fulfilment'">
        <div class="sm:col-span-6 flex flex-col">
            <CustomerDataForm :form="form" />
        </div>
        
        <div class="sm:col-span-6 flex flex-col">
            <label class="capitalize block text-sm font-medium text-gray-700">{{ trans("User Interests") }}</label>
            <div class="mt-2 gap-6 grid grid-cols-2">
                <!-- Loop through the interests -->
                <div
                    v-for="interest in interestsList"
                    :key="interest.value"
                    class="flex items-center space-x-3 border-2 py-3 px-6 rounded-lg transition-all duration-200 ease-in-out hover:bg-indigo-50 hover:shadow-lg cursor-pointer"
                    :class="{ 'bg-indigo-50 shadow-lg': form.interest.includes(interest.value) }"
                    @click="toggleInterest(interest.value)"
                >
                    <!-- Checkbox -->
                    <input
                        v-model="form.interest"
                        type="checkbox"
                        :id="interest.value"
                        :value="interest.value"
                        class="h-5 w-5 text-indigo-600 border-gray-300 rounded-sm focus:ring-2 focus:ring-indigo-500 cursor-pointer"
                        @click.stop
                    />
                    <label xfor="interest.value" class="select-none text-sm font-medium text-gray-900 cursor-pointer">
                        {{ interest.label }}
                    </label>
                </div>
                <p v-if="form.errors.interest" class="text-sm text-red-600 mt-1">{{ form.errors.interest }}</p>
            </div>
        </div>
    </template>

    <div class="sm:col-span-6">
        <hr />
    </div>
</template>