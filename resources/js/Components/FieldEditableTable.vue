<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { ref } from "vue"
import PureInput from "@/Components/Pure/PureInput.vue"
import { useForm } from "@inertiajs/vue3"
import { faCheckCircle, faTimesCircle } from "@fas"
import { faTrashAlt } from "@far"
import { faSignOutAlt } from "@fal"
import { get, isNull } from 'lodash-es'
import { faSpinnerThird } from '@fad'
import { cloneDeep } from 'lodash-es'
import PureMultiselect from "./Pure/PureMultiselect.vue"
import SelectQuery from "@/Components/SelectQuery.vue"

library.add(faTrashAlt, faSignOutAlt, faTimesCircle, faSpinnerThird, faCheckCircle)

const props = withDefaults(defineProps<{
    data: { [key: string]: string }
    fieldName: string
    placeholder?: string
    type?: string
    min?: number
    max?: number
    fieldType?: string
    options?: []
    required?:boolean
    urlRoute?:string
    label?:string,
    valueProp?:string
    isLoading?: boolean
}>(), {
    type: 'text',
    min: 0,
    max: 0,
    options: () => [],
    fieldType: 'input',
    required: true,
    label: 'label',
    valueProp: 'id'
})

const emits = defineEmits<{
    (e: 'onSave', data: {}, fieldName: string): void
    (e: 'input', event: {}): void
}>()


const pallet = ref(
    cloneDeep(
        {
            ...props.data,
            form: useForm({ ...props.data, [`${props.fieldName}`]: isNull(props.data[props.fieldName]) ? "" : props.data[props.fieldName] })
        }
    )
)


// On blur and press enter in Input
const onSaveInput = (value: string) => {
    if (value != props.data[props.fieldName]) {
        emits('onSave', pallet.value, props.fieldName)
    }
}

const onInput = (event) => {
    pallet.value.form.errors[props.fieldName] = ''
    emits('input', event)
}

const onChange = (value : Any) => {
   pallet.value.form[props.fieldName] = value
   onSaveInput(value)
}

</script>

<template>
    <div v-if="fieldType == 'input'" class="w-full">
        <PureInput v-model="pallet.form[fieldName]"
            @blur="(value) => onSaveInput(value)"
            @onEnter="(value) => onSaveInput(value)"
            @input="onInput" :suffix="true"
            :placeholder="placeholder"
            :type="type"
            :minValue="min"
            :maxValue="max"
        >
            <template #suffix>
                <div class="flex justify-center items-center px-2 absolute inset-y-0 right-0 gap-x-1">
                    <span v-if="get(pallet, ['form', 'processing'], false)">
                        <FontAwesomeIcon :icon="['fad', 'spinner-third']" class='animate-spin' fixed-width
                            aria-hidden="true" />
                    </span>
                    <span
                        v-if="!get(pallet, ['form', 'hasErrors'], false) && !get(pallet, ['form', 'processing'], false) && get(pallet, ['form', 'wasSuccessful'], false)">
                        <FontAwesomeIcon :icon="['fas', 'check-circle']" fixed-width class="text-green-500"
                            aria-hidden="true" />
                    </span>
                    <span
                        v-if="get(pallet, ['form', 'hasErrors'], false) && !get(pallet, ['form', 'processing'], false) && !get(pallet, ['form', 'wasSuccessful'], false)">
                        <FontAwesomeIcon :icon="['fas', 'times-circle']" fixed-width class="text-red-500"
                            aria-hidden="true" />
                    </span>
                </div>
            </template>
        </PureInput>
    </div>

    <div v-else-if="fieldType == 'select'" class="w-full">
        <PureMultiselect
            :modelValue="pallet.form[fieldName]"
            :placeholder="placeholder"
            :options="options"
            :isLoading
            caret
            @OnChange="onChange"
            :required="required"
            :label="label"
            :valueProp="valueProp"
            :mode="'single'"
        />
    </div>


    <div v-else-if="fieldType == 'selectQuery'" class="w-60 sm:w-24 md:w-24 lg:w-60">
        <SelectQuery
            :fieldName="fieldName"
            :placeholder="placeholder"
            :required="required"
            :urlRoute="urlRoute"
            :value="pallet.form"
            :label="label"
            :valueProp="valueProp"
            :on-change="onChange"
            :closeOnSelect="true"
            :options="props.options"
        >
        </SelectQuery>
    </div>


    <div v-if="get(pallet, ['form', 'errors', `${fieldName}`])" class="mt-1 mb-1 w-fit italic text-sm text-red-500">
        {{ get(pallet, ['form', 'errors', `${fieldName}`]) }}
    </div>
    <!-- <pre>{{ pallet.form.errors[fieldName] }}</pre> -->
</template>