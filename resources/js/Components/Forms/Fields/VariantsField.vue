<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 14 Mar 2023 23:44:10 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { faExclamationCircle, faCheckCircle } from '@fas'
import { faCopy } from '@fal'
import { faSpinnerThird } from '@fad'
import { library } from "@fortawesome/fontawesome-svg-core"
import PureVariantField from '@/Components/Pure/PureVariantField.vue'

library.add(faExclamationCircle, faCheckCircle, faSpinnerThird, faCopy)


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
    }
}>()

const emits = defineEmits()


</script>
<template>
    <div>
        <PureVariantField v-model="form[fieldName]" v-bind="fieldData" />
    </div>
</template>