<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 14 Mar 2023 23:44:10 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import PureVariantField from "@/Components/Pure/PureVariantField.vue"
import { get } from 'lodash-es'

defineOptions({ inheritAttrs: false })

const props = defineProps<{
    form: any
    fieldName: string
    options?: any
    fieldData?: {
        [key: string]: any
    }
}>()

console.log('form', props)
</script>

<template>
    <div class="relative">
        <PureVariantField v-model="form[fieldName]" v-bind="fieldData" :lockStructure="true" :withIsHide="true" />
        <p v-if="get(form, ['errors', fieldName])" class="mt-2 text-sm text-red-600" :id="`${fieldName}-error`">
            {{ form.errors[fieldName] }}
        </p>
    </div>
</template>
