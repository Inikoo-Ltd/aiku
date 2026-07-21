<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 14 Mar 2023 23:44:10 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">

import { get } from 'lodash-es'
import PureMultiplePriceCurrency from '@/Components/Pure/PureMultiplePriceCurrency.vue'

defineOptions({ inheritAttrs: false })

defineProps<{
    form: any
    fieldName: string
    options?: any
    fieldData?: {
        type: string
        readonly?: boolean
        currencies: Record<string, {
            currency: string
            currency_symbol?: string
            currency_id: number
            ratio_gbp: number | null
            ratio_eur: number | null
        }>
        visibleCurrencyCodes?: string[]
    }
}>()

</script>
<template>
    <div class="relative">
        <PureMultiplePriceCurrency
            :currencies="fieldData?.currencies ?? {}"
            :readonly="fieldData?.readonly"
            :visibleCurrencyCodes="fieldData?.visibleCurrencyCodes"
            v-model="form[fieldName]"
        />
        <p v-if="get(form, ['errors', `${fieldName}`])" class="mt-2 text-sm text-red-600" :id="`${fieldName}-error`">
            {{ form.errors[fieldName] }}
        </p>
    </div>
</template>