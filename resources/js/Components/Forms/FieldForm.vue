<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 14 Mar 2023 03:12:13 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">

import { useForm } from '@inertiajs/vue3'
import { routeType } from '@/types/route'
import { ref, computed } from 'vue'
import axios from 'axios'
import { getComponent } from '@/Composables/Listing/FieldFormList'  // Fieldform list

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faSave as fadSave, } from '@fad'
import { faSave as falSave, faInfoCircle } from '@fal'
import { faAsterisk, faQuestion } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(fadSave, faQuestion, falSave, faInfoCircle, faAsterisk)

const props = defineProps<{
    field: string
    refForms : any
    fieldData: {
        type: string
        label: string
        verification?: {
            route: routeType
            state: string
        }
        label_no_capitalize?: boolean  // To remove capitalize on label Fieldform
        value: any
        mode?: string
        required?: boolean
        options?: {}[]
        full: boolean
        noTitle?: boolean
        noSaveButton?: boolean  // Button: save
        updateRoute?: routeType
    }
    args: {
        updateRoute: routeType
    }
}>()

const updateRoute = props.fieldData.updateRoute || props.args['updateRoute']


let formFields = {
    [props.field]: props.fieldData.value,
}

if (props['fieldData']['hasOther']) {
    formFields[props['fieldData']['hasOther']['name']] = props['fieldData']['hasOther']['value']
}
formFields['_method'] = 'patch'
const form = useForm(formFields)
form['fieldType'] = 'edit'

const submit = () => {
    // PreserveScroll affect error in EpmloyeePosition (can't access layout)
    form.post(route(updateRoute.name, updateRoute.parameters), { preserveScroll: true })
}


const classVerification = ref('')
const isVerificationLoading = ref(false)
const labelVerification = ref('')
const verificationState = ref(props.fieldData?.verification?.state ?? '')
const stampDirtyValue = ref(props.fieldData.value ?? '')
const isVerificationDirty = computed(() => {
    return (stampDirtyValue.value !== form[props.field]) || verificationState.value === 'pending';
})

const checkVerification = async () => {
    isVerificationLoading.value = true
    try {
        const response = await axios.post(
            route(
                props.fieldData.verification?.route?.name,
                props.fieldData.verification?.route?.parameters
            ),
            { [props.field]: form[props.field] },
        )
        labelVerification.value = response.data?.message
        verificationState.value = response.data?.state
        classVerification.value = 'text-lime-500'

    }
    catch (error: any) {
        labelVerification.value = error.response?.data?.message
        classVerification.value = 'text-red-500'
    }
    isVerificationLoading.value = false

    stampDirtyValue.value = form[props.field]
}

defineExpose({
    form
})
</script>

<template>
    <form @submit.prevent="submit" class="divide-y divide-gray-200 w-full" :class="props.fieldData.full ? '' : 'max-w-2xl'">
        <dl class="pb-4 sm:pb-5 sm:grid sm:grid-cols-3 sm:gap-4 ">
            <!-- Title -->
            <dt v-if="!fieldData.noTitle && fieldData.label" class="text-sm font-medium text-gray-400" :class="props.fieldData.label_no_capitalize ? '' : 'capitalize'">
                <div class="inline-flex items-start leading-none">
                    {{ fieldData.label }}
                    <FontAwesomeIcon v-if="fieldData.required" icon="fas fa-asterisk" class="font-light text-[12px] text-red-400 mr-1"/>
                    <div v-if="fieldData.information" v-tooltip="fieldData.information" class="opacity-50 hover:opacity-100 cursor-pointer ml-1">
                        <FontAwesomeIcon icon="fal fa-info-circle" class="text-gray-500" fixed-width aria-hidden="true" />
                    </div>
                </div>
            </dt>

            <dd :class="props.fieldData.full ? 'sm:col-span-3' : fieldData.noTitle ? 'sm:col-span-3' : 'sm:col-span-2'" class="flex items-start text-sm text-gray-700 sm:mt-0">
                <div class="relative w-full">
                    <component :is="getComponent(fieldData.type)"
                        :key="field + fieldData.type"
                        :form="form"
                        :fieldName="field"
                        :options="fieldData.options"
                        :fieldData="fieldData"
                        :updateRoute
                        :refForms="refForms"
                    >
                    </component>

                    <!-- Verification: Label -->
                    <div v-if="labelVerification" class="mt-1" :class="classVerification">
                        <FontAwesomeIcon icon='fal fa-info-circle' class='opacity-80' aria-hidden='true' />
                        <span class="ml-1 font-medium">{{ labelVerification }}</span>
                    </div>
                </div>

                <!-- Button: Save -->
                <template v-if="fieldData.noSaveButton" />
                <span v-else class="ml-2 flex-shrink-0">
                    <button v-if="!fieldData.verification" class="h-9 align-bottom text-center" :disabled="form.processing || !form.isDirty" type="submit">
                        <template v-if="form.isDirty">
                            <FontAwesomeIcon v-if="form.processing" icon='fad fa-spinner-third' class='text-2xl animate-spin' fixed-width aria-hidden='true' />
                            <FontAwesomeIcon v-else icon="fad fa-save" class="h-8" :style="{ '--fa-secondary-color': 'rgb(0, 255, 4)' }" aria-hidden="true" />
                        </template>
                        <!-- <FontAwesomeIcon v-else-if="form.recentlySuccessful" icon="fas fa-check-circle" class="h-7 aspect-square text-green-500" aria-hidden="true" /> -->
                        <FontAwesomeIcon v-else icon="fal fa-save" class="h-8 text-gray-300" aria-hidden="true" />
                    </button>

                    <!-- Verification: Button -->
                    <span v-else>
                        <FontAwesomeIcon v-if="isVerificationLoading" icon='fad fa-spinner-third' class='animate-spin h-8 text-gray-500 hover:text-gray-600 cursor-pointer' aria-hidden='true' />
                        <FontAwesomeIcon v-else @click="isVerificationDirty ? checkVerification() : ''"
                            icon='fas fa-question'
                            class='h-8'
                            :class="isVerificationDirty ? 'text-gray-500 hover:text-gray-600 cursor-pointer' : 'text-gray-300'"
                            aria-hidden='true' />
                    </span>
                </span>
            </dd>
        </dl>
    </form>
</template>
