<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 14 Mar 2023 23:44:10 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
// import PureInput from "@/Components/Pure/PureInput.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faExclamationCircle, faCheckCircle, faPlus, faMinus } from '@fas'
import { faCopy } from '@fal'
import { faSpinnerThird } from '@fad'
import { library } from "@fortawesome/fontawesome-svg-core"
import { set, get } from 'lodash-es'
library.add(faExclamationCircle, faCheckCircle, faPlus, faMinus, faSpinnerThird, faCopy)
// import { ref, watch } from "vue"
import { InputNumber } from "primevue"

const props = defineProps<{
    form: any
    fieldName: string
    options?: any
    fieldData?: {
        type: string
        placeholder: string
        readonly?: boolean
        copyButton: boolean
        maxLength?: number
        bind: {

        }
    }
}>()

</script>
<template>
    <div class="relative">
        <div class="relative">
            <InputNumber
                v-model="form[fieldName]"
                xmodelValue="get(form, ['fieldName'], 0)"
                xupdate:modelValue="(e) => set(form, ['fieldName'], e.value)"
                inputId="horizontal-buttons"
                v-bind="fieldData?.bind"
                showButtons
                :step="1"
            >
                <template #incrementbuttonicon>
                    <FontAwesomeIcon :icon="faPlus" class="" fixed-width aria-hidden="true" />
                </template>
                <template #decrementbuttonicon>
                    <FontAwesomeIcon :icon="faMinus" class="" fixed-width aria-hidden="true" />
                </template>
            </InputNumber>

            <div class="absolute top-1/2 -translate-y-1/2 pointer-events-none right-6">
                <FontAwesomeIcon v-if="get(form, ['errors', `${fieldName}`])" fixed-width icon="fas fa-exclamation-circle"
                    class="h-5 w-5 text-red-500" aria-hidden="true" />
                <FontAwesomeIcon v-if="form.recentlySuccessful" fixed-width icon="fas fa-check-circle"
                    class="h-5 w-5 text-green-500" aria-hidden="true" />
                <FontAwesomeIcon v-if="form.processing" fixed-width icon="fad fa-spinner-third" class="h-5 w-5 animate-spin" />
            </div>
        </div>
    </div>
    <p v-if="get(form, ['errors', `${fieldName}`])" class="mt-2 text-sm text-red-600" :id="`${fieldName}-error`">
        {{ form.errors[fieldName] }}
    </p>
</template>