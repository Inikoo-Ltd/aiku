<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 14 Mar 2023 23:44:10 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import PureInput from "@/Components/Pure/PureInput.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faExclamationCircle, faCheckCircle } from '@fas'
import { faCopy } from '@fal'
import { faSpinnerThird } from '@fad'
import { library } from "@fortawesome/fontawesome-svg-core"
import { set, get } from 'lodash-es'
import ListSelector from "@/Components/ListSelector.vue"
import { routeType } from "@/types/route"
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
        withQuantity?: boolean
        routeFetch: routeType
    }
}>()


</script>
<template>
    <div class="relative">
        <div class="relative">
            <ListSelector :modelValue="form[fieldName]" :withQuantity="fieldData.withQuantity || false" :route-fetch="{
                name: 'grp.json.master-product-category.recommended-trade-units',
                parameters: { masterProductCategory: route().params['masterFamily'] }
            }" class="mt-4" />
        </div>

    </div>
    <p v-if="get(form, ['errors', `${fieldName}`])" class="mt-2 text-sm text-red-600" :id="`${fieldName}-error`">
        {{ form.errors[fieldName] }}
    </p>
</template>