<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 14 Mar 2023 23:44:10 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { RadioGroup, RadioGroupLabel, RadioGroupOption, RadioGroupDescription } from '@headlessui/vue'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faExclamationCircle, faCheckCircle } from '@fas'
import { faCopy } from '@fal'
import { faSpinnerThird } from '@fad'
import { library } from "@fortawesome/fontawesome-svg-core"
import { get } from 'lodash-es'
import PureDatePicker from '@/Components/Pure/PureDatePicker.vue'
import { trans } from 'laravel-vue-i18n'
library.add(faExclamationCircle, faCheckCircle, faSpinnerThird, faCopy)

const props = defineProps<{
    form: {
        [key: string]: {
            state: string
            employment_start_at: string
            employment_end_at: string
        } | string
    }
    fieldName: string
    options?: any
    fieldData: {
        options: {
            value: string
            title: string
            description: string
        }[]
    }
}>()

</script>

<template>
    <div class="relative space-y-4">
        <RadioGroup v-model="form[fieldName].state">
            <RadioGroupLabel class="text-base font-semibold leading-6 sr-only">Select the employee state</RadioGroupLabel>
            <div class="grid grid-cols-2 gap-x-3 gap-y-4 justify-around flex-wrap">
                <RadioGroupOption as="template" v-for="(option, index) in fieldData.options" :key="option.value" :value="option.value" v-slot="{ active, checked }">
                    <div :class="[
                        'relative flex cursor-pointer rounded-lg py-2 px-3 shadow-sm focus:outline-none',
                        checked ? 'bg-gray-100 ring-2 ring-indigo-600' : 'ring-1 ring-gray-300'
                    ]">
                    <!-- {{ compareObjects(form[fieldName], option) }} -->
                        <div class="flex flex-col gap-y-1">
                            <RadioGroupLabel as="span" class="select-none font-medium capitalize">{{ option.title }}</RadioGroupLabel>
                            <RadioGroupDescription as="span" class="select-none text-xs text-gray-400">{{ option.description }}</RadioGroupDescription>
                        <!-- <RadioGroupDescription as="span" class="mt-6 text-xs font-medium text-gray-600">{{ option.label }}</RadioGroupDescription> -->
                        </div>
                        <!-- <FontAwesomeIcon icon='far fa-check' :class="[!checked ? 'invisible' : '', 'h-4 w-4 text-indigo-600']" aria-hidden="true" /> -->
                        <!-- <span :class="[active ? 'border' : 'border-2', compareObjects(form[fieldName], option) ? 'border-indigo-600' : 'border-transparent', 'pointer-events-none absolute -inset-px rounded-lg']" aria-hidden="true" /> -->
                    </div>
                </RadioGroupOption>
            </div>
        </RadioGroup>

        <!-- Employment Start At -->
        <div v-if="form[fieldName].state === 'hired' || form[fieldName].state === 'working'" class="space-y-1">
            <div class="text-gray-500">{{ trans('Employee start date:')}}</div>
            <PureDatePicker v-model="form[fieldName].employment_start_at" placeholder="Enter employee date start" />
        </div>

        <!-- Employment End At -->
        <div v-if="form[fieldName].state === 'leaving' || form[fieldName].state === 'left'" class="space-y-1">
            <div class="text-gray-500">{{ trans('Employee end date:')}}</div>
            <PureDatePicker v-model="form[fieldName].employment_end_at" placeholder="Enter employee date end" />
        </div>
    </div>

    <p v-if="get(form, ['errors', `${fieldName}`])" class="mt-2 text-sm text-red-500" :id="`${fieldName}-error`">
        {{ form.errors[fieldName] }}
    </p>
</template>