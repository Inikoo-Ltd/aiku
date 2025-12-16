
<!--
* Author: Vika Aqordi
* Created on: 2025-12-11 16:34
* Github: https://github.com/aqordeon
* Copyright: 2025
-->

<script setup lang="ts">
import { faExclamationCircle, faCheckCircle } from '@fas'
import { faCopy } from '@fal'
import { faSpinnerThird } from '@fad'
import { library } from "@fortawesome/fontawesome-svg-core"
import { set, get } from 'lodash-es'
library.add(faExclamationCircle, faCheckCircle, faSpinnerThird, faCopy)
import { ref, watch } from "vue"
import PureShippingCountries from '@/Components/Pure/PureShippingCountries.vue'
import { routeType } from '@/types/route'

const props = defineProps<{
    form: any
    fieldName: string
    options?: any
    fieldData: {
        // type: string
        placeholder: string
        readonly?: boolean
        copyButton: boolean
        maxLength?: number
        country_list: {}[]
        routes: {
            store: routeType
            update: routeType
            delete: routeType
        }
        searchable: boolean
        mode: string // 'tags'
    }
}>()

</script>

<template>
    <div class="relative">
        <PureShippingCountries
            :modelValue="form[fieldName]"
            @update:modelValue="(e) => console.log('qqq', e)"
            :routes="fieldData?.routes"
            :country_list="fieldData?.country_list"
        />
    </div>

    <!-- <p v-if="get(form, ['errors', `${fieldName}`])" class="mt-2 text-sm text-red-600" :id="`${fieldName}-error`">
        {{ form.errors[fieldName] }}
    </p> -->
</template>