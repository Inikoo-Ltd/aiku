<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 14 Mar 2023 23:44:10 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { faExclamationCircle, faCheckCircle, faStar } from "@fas"
import { faCopy } from "@fal"
import { faSpinnerThird } from "@fad"
import { library } from "@fortawesome/fontawesome-svg-core"
import { set, get } from "lodash-es"
import ListSelector from "@/Components/ListSelectorForCreateMasterProduct.vue"
import { watch, ref } from "vue"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faSave as fadSave } from "@fad"
import { faSave as falSave, faInfoCircle } from "@fal"
import { notify } from "@kyvg/vue3-notification"
import axios from "axios"
import Dialog from "primevue/dialog"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { routeType } from "@/types/route"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"

library.add(fadSave, falSave, faInfoCircle)

library.add(faExclamationCircle, faCheckCircle, faSpinnerThird, faCopy)
defineOptions({ inheritAttrs: false })

const props = defineProps<{
    form: any
    fieldName: string
    options?: any
    fieldData: {
        type: string
        use_confirm: boolean
        withQuantity?: boolean
        routeFetch: routeType
        warn_modal_route: routeType
        key_quantity?: string
        tabs: {
            label: string
            routeFetch: routeType
        }[]
        is_dropship: boolean
        showSKOLabel: boolean
    }
}>()

const emits = defineEmits()
const showValidationDialog = ref(false)
const loadingValidation = ref(false)

const setFormValue = (data: Object, fieldName: String) => {
    if (Array.isArray(fieldName)) {
        return getNestedValue(data, fieldName)
    } else {
        return data[fieldName]
    }
}

const getNestedValue = (obj: Object, keys: Array) => {
    return keys.reduce((acc, key) => {
        if (acc && typeof acc === "object" && key in acc) return acc[key]
        return null
    }, obj)
}

const value = ref(setFormValue(props.form, props.fieldName))

watch(value, (newValue) => {
    updateFormValue(newValue)
    props.form.errors[props.fieldName] = ""
})

const updateFormValue = (newValue) => {
    let target = props.form
    if (Array.isArray(props.fieldName)) {
        set(target, props.fieldName, newValue)
    } else {
        target[props.fieldName] = newValue
    }
    emits("update:form", target)
}

const validationResult = ref<Object|null>(null)

const checkValidation = async () => {
    loadingValidation.value = true
    showValidationDialog.value = true
    try {
        await axios.get(
            route(props.fieldData.warn_modal_route.name, props.fieldData.warn_modal_route.parameters)
        ).then((response) => {
            validationResult.value = response.data
        })

    } catch (error) {
        console.log(error)
        notify({
            title: trans("Something went wrong."),
            text: trans("Failed to get the options list"),
            type: "error"
        })
    } finally {
        loadingValidation.value = false
    }
}

const onSave = () => {
    showValidationDialog.value = false
    emits("submit")
}

watch(showValidationDialog, (value) => {
    if (!value) {
        validationResult.value = null
    }
})

</script>
<template>
    <div class="relative">
        <div class="relative flex gap-4">
            <div :class="fieldData.use_confirm ? 'w-[90%]' : 'w-full'">
                <ListSelector 
                    :modelValue="value" 
                    @update:modelValue="(e) => updateFormValue(e)" 
                    v-bind="fieldData" 
                />
            </div>

            <div v-if="fieldData.use_confirm">
                <div class="h-9 align-bottom text-center" :disabled="form.processing || !form.isDirty || loadingValidation">
                    <template v-if="form.isDirty">
                        <div @click="checkValidation">
                            <FontAwesomeIcon v-if="form.processing || loadingValidation" icon="fad fa-spinner-third"
                                             class="text-2xl animate-spin" fixed-width aria-hidden="true" />
                            <FontAwesomeIcon v-else icon="fad fa-save" class="h-8"
                                             :style="{ '--fa-secondary-color': 'rgb(0, 255, 4)' }" aria-hidden="true" />
                        </div>
                    </template>
                    <FontAwesomeIcon v-else icon="fal fa-save" class="h-8 text-gray-300" aria-hidden="true" />
                </div>
            </div>
        </div>
    </div>

    <p v-if="get(form, ['errors', `${fieldName}`])" class="mt-2 text-sm text-red-600" :id="`${fieldName}-error`">
        {{ form.errors[fieldName] }}
    </p>

    <Dialog v-model:visible="showValidationDialog" :closable="true" modal :style="{ width: '50rem' }">
        <template #header>
            <div class="flex items-center gap-2 text-lg items-center">
                <FontAwesomeIcon :icon="faExclamationCircle" class="text-orange-500 my-auto" />
                <span class="font-semibold ">{{ trans("Trade Units detail will be changed") }}</span>
            </div>
        </template>

        <div class="text-md font-medium pb-1">
            <span>
                {{ trans("Organisation Stocks data would be affected. This would affect current & future picking data") }}
            </span>
        </div>

       <div v-if="loadingValidation" class="flex py-2">
            <LoadingIcon
                class="mx-auto"
            />
       </div>
       <div v-else>
            <div v-if="validationResult?.to_be_modified.length" class="text-sm pt-2 font-medium">
                {{ ctrans('These orders will be modified automatically') }}:
                <div class="grid border border-green-200 rounded-md bg-green-100 px-2 py-2 gap-2 max-h-[10vw] overflow-x-hide overflow-y-auto" style="scrollbar-width: thin;">
                    <span v-for="deliveryNote in validationResult?.to_be_modified" class="">
                        • {{ deliveryNote.reference }} | {{ deliveryNote.shop_name }} 
                        <FontAwesomeIcon v-if="!deliveryNote.delivery_note_is_premium_dispatch" v-tooltip="trans('Priority dispatch')" :icon="faStar" class="text-yellow-500 animate-bounce" fixed-width aria-hidden="true" />
                    </span>
                </div>
            </div>

            <div v-if="validationResult?.to_be_affected.length" class="text-sm pt-2 font-medium">
                {{ ctrans('These orders will be affected, and would need manual actions') }}:
                <div class="grid border border-red-200 rounded-md bg-red-100 px-2 py-2 gap-2 max-h-[10vw] overflow-x-hide overflow-y-auto" style="scrollbar-width: thin;">
                    <span v-for="deliveryNote in validationResult?.to_be_affected" class="">
                        • {{ deliveryNote.reference }} | {{ deliveryNote.shop_name }} 
                        <FontAwesomeIcon v-if="!deliveryNote.delivery_note_is_premium_dispatch" v-tooltip="trans('Priority dispatch')" :icon="faStar" class="text-yellow-500 animate-bounce" fixed-width aria-hidden="true" />
                    </span>
                </div>
            </div>
       </div>


        <template #footer>
            <div class="flex justify-end gap-2">
                <Button label="Cancel" type="tertiary" @click="showValidationDialog = false" />
                <Button label="Confirm" type="negative" @click="onSave" />
            </div>
        </template>
    </Dialog>
</template>