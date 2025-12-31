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
import { watch } from 'vue'
import { notify } from '@kyvg/vue3-notification'
import { trans } from 'laravel-vue-i18n'

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

watch(() => props.form.errors, (errorBag) => {
    if(Object.keys(errorBag).length === 0) return;
    const errorBagUnique = errorBag ? new Set(Object.values(errorBag).flat()) : [];
    notify({
        title: "Something went wrong",
        data: {
            html: errorBagUnique ? [...errorBagUnique].join('<br>') : trans("Please try again or contact administrator")
        },
        type: 'error',
        duration: 5000
    });
})
</script>
<template>
    <div>
        <PureVariantField v-model="form[fieldName]" v-bind="fieldData" />
    </div>
</template>