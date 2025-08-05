<script setup lang="ts">
import PureInput from "@/Components/Pure/PureInput.vue"
import { set, get } from "lodash-es"
import { ref, onMounted } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faSpinnerThird, faCopy } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import axios from "axios"
import { trans } from "laravel-vue-i18n"
import PureMultiselect from "@/Components/Pure/PureMultiselect.vue"

library.add(faSpinnerThird, faCopy)

const props = defineProps<{
    form: any
    fieldName: string
    options?: any
    refForms?: any
    fieldData?: {
        route_validate?: {
            name: string
            parameters?: any
        }
    }
}>()

const emits = defineEmits(["update:form"])

const value = ref(getFormValue(props.form, props.fieldName))
const isLoadingButton = ref(false)
const validationStatus = ref<"success" | "failed" | null>(null)
const needButtonValidate = ref(false)

function getFormValue(data: any, fieldName: string | string[]) {
    return Array.isArray(fieldName)
        ? fieldName.reduce((acc, key) => acc?.[key], data)
        : (data[fieldName] ?? { value: "" })
}

function updateFormValue(newValue: any) {
    if (Array.isArray(props.fieldName)) {
        set(props.form, props.fieldName, newValue)
    } else {
        props.form[props.fieldName] = newValue
    }
    emits("update:form", props.form)
}

function updateVat() {
    updateFormValue(value.value)
}

async function validateVatNumber() {
    if (!props.fieldData?.route_validate) return

    isLoadingButton.value = true
    validationStatus.value = null
    set(props.form.errors, props.fieldName, null)

    try {
        const { name, parameters } = props.fieldData.route_validate
        const { data } = await axios.post(route(name, parameters ?? {}))
        console.log(data)

        validationStatus.value = data.valid ? "success" : "failed"
        set(props.form.errors, props.fieldName, null) // clear error if valid
    } catch (err) {
        console.error(err)
        validationStatus.value = "failed"
        set(
            props.form.errors,
            props.fieldName,
            trans("Failed to validate tax number, please try again.")
        )
    } finally {
        isLoadingButton.value = false
    }
}

onMounted(() => {
    needButtonValidate.value = !!props.form[props.fieldName]?.number
})

console.log("TaxNumber component mounted with fieldName:", props)
</script>

<template>
    <div class="space-y-3 mb-5">
        <!-- Input Nomor VAT -->
        <PureInput v-model="value.number" @update:model-value="updateVat"
            :class="{ 'border-red-500': form.errors?.[fieldName] }" />
        <!-- Validasi Berhasil -->
        <p v-if="validationStatus === 'success'" class="text-sm text-green-600">
            {{ trans("Tax number is valid.") }}
        </p>

         <p v-if="validationStatus === 'failed'" class="text-sm text-red-600">
            {{ trans("Tax number is not valid.") }}
        </p>

        <!-- Tombol Validasi Nomor Pajak -->
        <button v-if="needButtonValidate" type="button"
            class="inline-flex items-center text-blue-600 underline hover:text-blue-800 transition disabled:text-gray-400 disabled:cursor-not-allowed"
            @click="validateVatNumber" :disabled="isLoadingButton">
            <svg v-if="isLoadingButton" class="animate-spin h-4 w-4 mr-2 text-blue-600"
                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
            </svg>
            <span>{{ trans("Validate Tax Number") }}</span>
        </button>

        <!-- Select Tipe VAT -->
        <PureMultiselect :modelValue="value.type" :placeholder="'type'" :options="fieldData?.typeOptions"
            :label="'label'" :valueProp="'value'" :mode="'single'" :required="true"
            @update:model-value="(v) => { value.type = v; updateFormValue(value) }" />


        <!-- Error dari Server -->
        <p v-if="get(form, ['errors', fieldName])" class="text-sm text-red-600" :id="`${fieldName}-error`">
            {{ form.errors[fieldName] }}
        </p>
    </div>
</template>
