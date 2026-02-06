<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 14 Mar 2023 03:12:13 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">

import { router, useForm } from '@inertiajs/vue3'
import { routeType } from '@/types/route'
import { ref, computed, watch } from 'vue'
import axios from 'axios'
import { getComponent } from '@/Composables/Listing/FieldFormList'  // Field form list
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faSave as fadSave, } from '@fad'
import { faSave as falSave, faInfoCircle } from '@fal'
import { faAsterisk, faQuestion } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
import Modal from '../Utils/Modal.vue'
import { trans } from 'laravel-vue-i18n'
import Button from '../Elements/Buttons/Button.vue'
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
        value: any
        mode?: string
        required?: boolean
        options?: {}[]
        full: boolean
        noTitle?: boolean
        noSaveButton?: boolean  // Button: save
        updateRoute?: routeType
        isWithRefreshFieldForm?: boolean
        revisit_after_save?: boolean
        saveConfirmation?: {   // On click save will show modal confirmation
            title?: string
            description?: string
            yesLabel?: string
        }
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
    if (props.fieldData?.confirmation?.description) {
        const confirmed = confirm(props.fieldData.confirmation.description)

        if (confirmed) {
            form.post(
                route(updateRoute.name, updateRoute.parameters), 
                { 
                    preserveScroll: true,
                    onSuccess: () => {
                        if(props.fieldData.revisit_after_save){
                            router.reload()
                        }
                    }
                }
            )
        } else {
            return
        }
    }

    else {
        form.post(
            route(updateRoute.name, updateRoute.parameters), 
            { 
                preserveScroll: true,
                onSuccess: () => {
                    if(props.fieldData.revisit_after_save){
                        router.reload()
                    }
                    isModalConfirmation.value = false
                },
            }
        )
    }


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

// Section: refresh value when successfully saved (case: add Tags)
watch(() => props?.fieldData?.value, (newValue) => {
    if (props.fieldData?.isWithRefreshFieldForm) {
        form.defaults({
            [props.field]: newValue
        })
        form.reset()
    }
})

defineExpose({
    form
})

const isModalConfirmation = ref(false)
</script>

<template>
    <form @submit.prevent="submit" class="divide-y divide-gray-200 w-full" :class="props.fieldData.full ? '' : 'max-w-2xl'">
        <dl class="pb-4 sm:pb-5 sm:grid sm:grid-cols-3 sm:gap-4 ">
            <!-- Title -->
            <dt v-if="!fieldData.noTitle && fieldData.label" class="qwezxctext-sm font-medium text-gray-400">
                <div class="inline-flex items-start leading-none">
                    {{ fieldData.label }}
                    <FontAwesomeIcon v-if="fieldData.required" icon="fas fa-asterisk" class="font-light text-[12px] text-red-400 mr-1"/>
                    <div v-if="fieldData.information" v-tooltip="fieldData.information" class="opacity-50 hover:opacity-100 cursor-pointer ml-1">
                        <FontAwesomeIcon icon="fal fa-info-circle" class="text-gray-500" fixed-width aria-hidden="true" />
                    </div>
                </div>
                
                <!-- Section: Warning -->
                <div v-if="fieldData.warning" v-tooltip="fieldData.warning" class="my-2 text-xs border border-amber-500 rounded-sm bg-amber-100 py-1 px-2 text-balance text-amber-600">
                    <FontAwesomeIcon icon="fas fa-exclamation-triangle" class="text-amber-500" fixed-width aria-hidden="true" />
                    {{ fieldData.warning }}
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
                        :submit="submit"
                        @submit="submit"
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
                <span v-else-if="fieldData.save" class="ml-2 flex-shrink-0">

                </span>
                <span v-else class="ml-2 flex-shrink-0">
                    <div v-if="fieldData.saveConfirmation"
                        @click="() => isModalConfirmation = true"
                        class="h-9 align-bottom text-center cursor-pointer"
                        :disabled="form.processing || !form.isDirty"
                    >
                        <template v-if="form.isDirty">
                            <FontAwesomeIcon v-if="form.processing" icon='fad fa-spinner-third' class='text-2xl animate-spin' fixed-width aria-hidden='true' />
                            <FontAwesomeIcon v-else icon="fad fa-save" class="h-8" :style="{ '--fa-secondary-color': 'rgb(0, 255, 4)' }" aria-hidden="true" />
                        </template>
                        <FontAwesomeIcon v-else icon="fal fa-save" class="h-8 text-gray-300" aria-hidden="true" />
                    </div>

                    <button v-else-if="!fieldData.verification" class="h-9 align-bottom text-center" :disabled="form.processing || !form.isDirty" type="submit">
                        <template v-if="form.isDirty">
                            <FontAwesomeIcon v-if="form.processing" icon='fad fa-spinner-third' class='text-2xl animate-spin' fixed-width aria-hidden='true' />
                            <FontAwesomeIcon v-else icon="fad fa-save" class="h-8" :style="{ '--fa-secondary-color': 'rgb(0, 255, 4)' }" aria-hidden="true" />
                        </template>
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

        <!-- Modal: Save confirmation -->
        <Modal v-if="fieldData.saveConfirmation" :isOpen="isModalConfirmation" @onClose="() => isModalConfirmation = false" width="w-full max-w-lg">
            <div class="relative text-left sm:w-full sm:max-w-lg py-2 flex">

                <div
                    class="mx-auto flex size-12 shrink-0 items-center justify-center rounded-full bg-amber-100 sm:mx-0 sm:size-10">
                    <FontAwesomeIcon
                        icon="fal fa-exclamation-triangle"
                        class="text-amber-600"
                        fixed-width
                        aria-hidden="true" />
                </div>

                <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                    <div class="text-base font-semibold">
                        {{ fieldData.saveConfirmation?.title ?? trans("Are you sure want to pick all from magic place?") }}
                    </div>
                    <div class="mt-2">
                        <p class="text-sm text-gray-500">
                            {{ fieldData.saveConfirmation?.description ?? trans("I understand what I did.") }}
                        </p>
                    </div>

                    <div class="mt-5 flex xflex-row-reverse gap-2">
                        <Button
                            type="tertiary"
                            icon="far fa-arrow-left"
                            :disabled="form.processing"
                            :label="trans('Cancel')"
                            full
                            @click=" () => (isModalConfirmation = false)"
                        />
                        <div class="xw-full sm:w-fit">
                            <Button
                                @click="() => submit()"
                                type="secondary"
                                key="3"
                                :loading="form.processing"
                                full
                            >
                                <template #label>
                                    <div class="whitespace-nowrap">
                                        {{ fieldData.saveConfirmation?.yesLabel ?? trans("Yes, update it") }}
                                    </div>
                                </template>
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </Modal>
    </form>
</template>
