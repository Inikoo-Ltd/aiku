<script setup lang="ts">
import { faExclamationCircle, faCheckCircle } from '@fas'
import { faCopy } from '@fal'
import { faSpinnerThird } from '@fad'
import { library } from "@fortawesome/fontawesome-svg-core"
import TabSelector from '@/Components/Elements/TabSelector.vue'
import { get } from 'lodash-es'
library.add(faExclamationCircle, faCheckCircle, faSpinnerThird, faCopy)

const props = defineProps<{
    form: any
    fieldName: string
    fieldData: any  // declare to avoid warning
    options: {
        value: string
        label: string
    }[]
}>()

</script>

<template>
    <div class="relative">
        <TabSelector :optionRadio="options" :radioValue="form[fieldName]" />
    </div>
    <p v-if="get(form, ['errors', `${fieldName}`])" class="mt-2 text-sm text-red-600" :id="`${fieldName}-error`">
        {{ form.errors[fieldName] }}
    </p>
</template>