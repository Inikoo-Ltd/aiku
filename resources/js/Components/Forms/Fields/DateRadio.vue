<!--
  - Author: Vika <aqordivika@yahoo.co.id>
  - Created: Tue, 14 Mar 2023 23:44:10 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
-->

<script setup lang="ts">
import PureCheckbox from '@/Components/Pure/PureCheckbox.vue'
import Popover from '@/Components/Popover.vue'
import { get } from 'lodash-es'
import { useOrdinalSuffix } from '@/Composables/Utils'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import { trans } from 'laravel-vue-i18n'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { routeType } from '@/types/route'

const props = defineProps<{
    form: any
    fieldName: string
    fieldData: {
        type: string
        placeholder: string
        options: number[]
        readonly?: boolean
        copyButton: boolean
        maxLength?: number
    }
    updateRoute: routeType
}>()

const onSelectOption = (value: number) => {
    props.form.errors[props.fieldName] = false
    props.form[props.fieldName].date = value
}

// Section: Replace the button from fieldform
const onSaveRecurringBills = (updateAll: boolean) => {
    props.form.transform((data) => ({
        ...data,
        update_all: updateAll,
    }))
    .post(route(props.updateRoute.name, props.updateRoute.parameters), { preserveScroll: true })
}
</script>


<template>
    <div class="flex justify-between">
        <div class="relative" :class="get(form, ['errors', `${fieldName}`]) ? 'errorShake' : ''">
            <Popover>
                <template #button="{ open, close }">
                    <div class="relative cursor-pointer underline">
                        <div class="flex gap-x-1">
                            <span class="relative">
                                <Transition name="spin-to-down">
                                    <div :key="form[fieldName].date" class="">{{ typeof form[fieldName].date === 'number' ? useOrdinalSuffix(form[fieldName].date) : form[fieldName].date.replace('_', ' ') }}</div>
                                </Transition>
                            </span>
                            on each month
                            <!-- <span class="relative">
                                <Transition name="spin-to-down"><div v-if="form[fieldName].isWeekdays" class="text-gray-500">(weekdays only)</div></Transition>
                            </span> -->
                        </div>
                    </div>
                </template>
                <template #content="{ open, close }">
                    <div class="mb-3">
                        <div class="w-80 grid grid-cols-5 gap-x-2 gap-y-1.5 mt-4">
                            <div
                                v-for="option in fieldData.options"
                                @click="() => onSelectOption(option)"
                                class="px-3 py-2 flex items-center justify-center rounded border border-gray-300 cursor-pointer select-none"
                                :class="option == form[fieldName].date ? 'bg-indigo-600 text-white' : 'bg-white hover:bg-gray-100'"
                            >
                                {{ option }}
                            </div>
                            <div @click="() => onSelectOption('last_day')"
                                class="col-span-3 px-3 py-2 flex items-center justify-center rounded border border-gray-300 cursor-pointer select-none"
                                :class="form[fieldName].date == 'last_day'  ? 'bg-indigo-600 text-white' : 'bg-white hover:bg-gray-100'"
                            >
                                Last day of month
                            </div>
                        </div>
                    </div>
                    <!-- <div class="flex items-center">
                        <label for="weekday_checkbox" class="cursor-pointer pr-3 select-none">Only on weekday</label>
                        <PureCheckbox v-model="form[fieldName].isWeekdays" @update:modelValue="() => props.form.errors[props.fieldName] = false" id="weekday_checkbox" />
                    </div> -->
                </template>
            </Popover>
            
            <p v-if="form.errors[`${fieldName}.date`]" class="mt-2 text-sm text-red-600" :id="`${fieldName}-error`">
                {{ form.errors[`${fieldName}.date`] }}
            </p>
            <p v-if="form.errors[`${fieldName}.isWeekdays`]" class="mt-2 text-sm text-red-600" :id="`${fieldName}-error`">
                {{ form.errors[`${fieldName}.isWeekdays`] }}
            </p>
        </div>
        
        <div class="">
            <LoadingIcon v-if="form.processing" class="text-[23px]" />
            <Popover v-else-if="form.isDirty && updateRoute?.name">
                <template #button="{ open }">
                    <FontAwesomeIcon icon="fad fa-save" class="h-8" :style="{ '--fa-secondary-color': 'rgb(0, 255, 4)' }" aria-hidden="true" />
                </template>
                
                <template #content="{ close: closed }">
                    <div class="max-w-min">
                        <div class="mb-3 w-fit text-xs text-gray-600">{{trans('Apply the new cut off for the next bills only? Or apply for current open bills and next bills?')}}</div>
                        <div class="flex gap-x-2">
                            <Button @click="() => onSaveRecurringBills(false)" :label="trans('Next bills only')" type="tertiary" />
                            <Button @click="() => onSaveRecurringBills(true)" :label="trans('Current open bills and next bills')" />
                        </div>

                        <div v-if="form.processing" class="absolute inset-0 bg-black/20 rounded-md flex justify-center items-center">
                            <LoadingIcon class="text-white text-4xl" />
                        </div>
                    </div>
                </template>
            </Popover>

            <FontAwesomeIcon v-else icon="fal fa-save" class="h-8 text-gray-300" aria-hidden="true" />
            <!-- <pre>{{ form }}</pre> -->
        </div>
    </div>
</template>