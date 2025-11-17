<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 14 Mar 2023 23:44:10 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import PickList from 'primevue/picklist';
import { ref, onMounted, toRaw } from 'vue';
import Accordion from 'primevue/accordion';
import AccordionPanel from 'primevue/accordionpanel';
import AccordionHeader from 'primevue/accordionheader';
import AccordionContent from 'primevue/accordioncontent';
import Multiselect from "@vueform/multiselect"
import { trans } from 'laravel-vue-i18n';
import Tag from '@/Components/Tag.vue';
import PureInput from '@/Components/Pure/PureInput.vue';
import PureMultiselect from '@/Components/Pure/PureMultiselect.vue';
import Popover from 'primevue/popover';
import Avatar from 'primevue/avatar';
import Icon from '@/Components/Icon.vue';
import { useFormatTime } from '@/Composables/useFormatTime'
import { Link, router } from '@inertiajs/vue3'

import { faExclamationCircle, faCheckCircle, faCircle } from '@fas'
import { faThumbsDown, faChair, faLaugh, faCopy, faUser, faSearch } from '@fal';
import { faSpinnerThird } from '@fad'
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { fromPairs } from 'lodash-es';
import PureInputNumber from '@/Components/Pure/PureInputNumber.vue';

library.add(faExclamationCircle, faCheckCircle, faSpinnerThird, faCopy, faThumbsDown, faChair, faLaugh, faCopy, faUser)

const props = defineProps<{
    form: any
    fieldName: string
    options?: any
    refForms?:any
    fieldData?: {
        type: string
        placeholder: string
        readonly?: boolean
        copyButton: boolean
        maxLength?: number
    }
}>()


const emits = defineEmits<{
    (e: 'update:modelValue', value: string | number): void
}>()

const queryData = ref({
    day: 1,
    range: 'week'
})
const contact = [
    { label: trans("Never"), value: false },
    { label: trans("Last Contact"), value: true },
]
const logic = [
    { label: trans("All"), value: "all" },
    { label: trans("Any"), value: "any" },
]
const Intervals = [
    {
        label: 'Day',
        value: 'day'
    },
    {
        label: 'Week',
        value: 'week'
    },
    {
        label: 'Month',
        value: 'month'
    }
]
const search = ref(null)
const prospect = ref(null);
const _popover = ref()

const onChangeQuery = (data = null) => {
    const updatedForm = { ...props.form }; // Create a shallow copy
    updatedForm[props.fieldName].query = data; // Update the specific nested property
    emits('update:modelValue', updatedForm); // Emit the updated object
};

const toggle = (event) => {
    event.preventDefault()
    _popover.value.toggle(event);
}

const onChangeCustomLastContact = (event) => {
    if (!props.form[props.fieldName].custom_prospects_query.last_contact.interval) {
        props.form[props.fieldName].custom_prospects_query.last_contact.interval = {
            day: 1,
            range: 'week'
        }
    }
}

/* const getPropspects = () =>{
    router.get(route(props.options.query.name, props.options.query.parameters), {
    onFinish: (response) => {
        console.log('Request finished', response);
    },
    onSuccess: (response) => {
        console.log('Request successful', response);
    },
    onError: (error) => {
        console.error('Request failed', error);
    },
});
} */

onMounted(() => {
    prospect.value = [props.options.query.data, []]
   /*  getPropspects() */
})

</script>
<template>
    <!-- query -->

    <div v-if="form.recipient_type == 'query' || (refForms?.[2] && refForms[2].form?.recipient_type == 'query')" class="grid grid-cols-1 md:grid-cols-8 gap-2">
        <div class="md:col-span-8 grid sm:grid-cols-1 md:grid-cols-4 gap-2 h-auto mb-3">
            <div :class="form[fieldName].query ? 'from-blue-500 to-sky-300' : 'from-gray-500  to-gray-300'"
                @click="() => onChangeQuery(queryData)"
                class="bg-gradient-to-tr text-white flex flex-col justify-between px-6 py-2 rounded-lg shadow-lg sm:h-auto">
                <div class="flex justify-between items-center mb-2">
                    <div>
                        <div class="text-[15px] font-semibold capitalize">{{trans("Prospects last contacted (within interval)")}}
                        </div>
                    </div>
                    <FontAwesomeIcon :icon="form[fieldName].query ? faCheckCircle : faCircle" class="text-xl" />
                </div>
                <div @contextmenu="toggle">
                    <div class="text-2xl font-bold capitalize">{{ queryData?.day ?? 1 }} {{ queryData?.range ?? 'week' }}</div>
                    <div class="text-sm text-white/80">{{trans("have a 4567 people to send")}}</div>
                    <Popover ref="_popover">
                        <div class="flex  gap-4 w-[15rem]">
                            <div class="w-1/4">
                                <PureInputNumber v-model="queryData.day" :min-value="1"></PureInputNumber>
                            </div>
                            <div class="w-3/4">
                                <PureMultiselect v-model="queryData.range" placeholder="Interval" :options="Intervals"
                                    caret :required="true" label="label" valueProp="value" :mode="'single'" />
                            </div>
                        </div>
                    </Popover>
                </div>
            </div>


            <div :class="!form[fieldName].query ? 'from-blue-500 to-sky-300' : 'from-gray-500  to-gray-300'"
                @click="() => onChangeQuery(null)"
                class="bg-gradient-to-tr text-white flex flex-col justify-between px-6 py-2 rounded-lg shadow-lg sm:h-auto">
                <div class="flex justify-between items-center mb-2">
                    <div>
                        <div class="text-[15px] font-semibold capitalize">{{trans("Prospects not contacted")}}</div>
                    </div>
                    <FontAwesomeIcon :icon="!form[fieldName].query ? faCheckCircle : faCircle" class="text-xl" />
                </div>
                <div>
                    <div class="text-2xl font-bold">-</div>
                    <div class="text-sm text-white/80">{{trans("have a 4567 people to send")}}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- custom -->
    <div v-if="form.recipient_type == 'custom' || (refForms?.[2] && refForms[2].form?.recipient_type == 'custom')" class="card">
        <div class="p-2 bg-gray-100 rounded-lg">
            <Accordion value="0">
                <AccordionPanel value="0">
                    <AccordionHeader>Tags</AccordionHeader>
                    <AccordionContent>
                        <div>
                            <div class="mb-2">
                                <span class="font-bold text-sm block mb-1">{{ trans("Included Tags") }} :</span>
                                <Multiselect v-model="form[fieldName].custom_prospects_query.tags.tag_ids" mode="tags"
                                    placeholder="Select the tag" valueProp="id" trackBy="name" label="name"
                                    :close-on-select="false" :searchable="true" :caret="false" :options="options.tags.data"
                                    noResultsText="No one left. Type to add new one.">

                                    <template
                                        #tag="{ option, handleTagRemove, disabled }: { option: tag, handleTagRemove: Function, disabled: boolean }">
                                        <div class="px-0.5 py-[3px]">
                                            <Tag :theme="option.id" :label="option.name" :closeButton="true"
                                                :stringToColor="true" size="sm"
                                                @onClose="(event) => handleTagRemove(option, event)" />
                                        </div>
                                    </template>
                                </Multiselect>
                            </div>

                            <div v-if="form[fieldName].custom_prospects_query.tags.tag_ids?.length > 1" class="mb-4">
                                <div class="mt-1">
                                    <fieldset>
                                        <div class="space-y-4 sm:flex sm:items-center sm:space-x-10 sm:space-y-0">
                                            <div v-for="(filter, filterIndex) in logic" :key="filter.value"
                                                class="flex items-center">
                                                <input :id="filter.value" :name="'logic' + fieldName" type="radio"
                                                    :value="filter.value"
                                                    class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-600"
                                                    v-model="form[fieldName].custom_prospects_query.tags.logic" />
                                                <label :for="filter.value"
                                                    class="ml-3 block text-xs font-medium leading-6 text-gray-900">{{ filter.label }}</label>
                                            </div>
                                        </div>
                                    </fieldset>
                                </div>
                            </div>

                            <div>
                                <span class="font-bold text-sm block mb-2">{{ trans("Tags not included") }} :</span>
                                <Multiselect v-model="form[fieldName].custom_prospects_query.tags.negative_tag_ids"
                                    mode="tags" placeholder="Select the tag" valueProp="id" trackBy="name" label="name"
                                    :close-on-select="false" :searchable="true" :caret="false" :options="options.tags.data"
                                    noResultsText="No one left. Type to add new one.">

                                    <template
                                        #tag="{ option, handleTagRemove, disabled }: { option: tag, handleTagRemove: Function, disabled: boolean }">
                                        <div class="px-0.5 py-[3px]">
                                            <Tag :theme="option.id" :label="option.name" :closeButton="true"
                                                :stringToColor="true" size="sm"
                                                @onClose="(event) => handleTagRemove(option, event)" />
                                        </div>
                                    </template>
                                </Multiselect>
                            </div>

                        </div>
                    </AccordionContent>
                </AccordionPanel>

                <AccordionPanel value="1">
                    <AccordionHeader>Prospect Last Contacted</AccordionHeader>
                    <AccordionContent>
                        <div>
                            <fieldset>
                                <div class="space-y-4 sm:flex sm:items-center sm:space-x-10 sm:space-y-0">
                                    <div v-for="(item, index) in contact" :key="item.value" class="flex items-center">
                                        <input :id="item.value" :name="'logic' + fieldName" type="radio"
                                            :value="item.value" @input="onChangeCustomLastContact"
                                            class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-600"
                                            v-model="form[fieldName].custom_prospects_query.last_contact.use_contact" />
                                        <label :for="item.value"
                                            class="ml-3 block text-xs font-medium leading-6 text-gray-900">{{
                                            item.label }}</label>
                                    </div>
                                </div>
                            </fieldset>

                            <div v-if="form[fieldName].custom_prospects_query.last_contact.use_contact"
                                class="flex flex-col gap-y-2 mt-4">
                                <div class="flex gap-x-2">
                                    <div class="w-20">
                                        <PureInput type="number" :minValue="1" :caret="false" placeholder="range"
                                            v-model="props.form[props.fieldName].custom_prospects_query.last_contact.interval.day" />
                                    </div>
                                    <div class="w-full">
                                        <PureMultiselect
                                            v-model="props.form[props.fieldName].custom_prospects_query.last_contact.interval.range"
                                            placeholder="Interval" :options="Intervals" caret :required="true"
                                            label="label" valueProp="value" :mode="'single'" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </AccordionContent>
                </AccordionPanel>

            </Accordion>
        </div>
    </div>

    <!-- prospect -->
    <div v-if="form.recipient_type == 'prospect' || (refForms?.[2] && refForms[2].form?.recipient_type == 'prospect')" class="card">
        <PickList v-model="prospect" dataKey="id">
            <template #sourceheader>
                <div class="border-b p-3">
                    <PureInput v-model="search" placeholder="Search" :suffix="true">
                    <template #suffix>
                        <div
                            class="flex justify-center items-center px-2 absolute inset-y-0 right-0 gap-x-1 cursor-pointer opacity-20 hover:opacity-75 active:opacity-100">
                            <FontAwesomeIcon :icon="faSearch" />
                        </div>
                    </template>
                </PureInput>
                </div>
            </template>
            <template #targetheader>
                <div class="border-b p-3">
                    <PureInput v-model="search" placeholder="Search" :suffix="true">
                    <template #suffix>
                        <div
                            class="flex justify-center items-center px-2 absolute inset-y-0 right-0 gap-x-1 cursor-pointer opacity-20 hover:opacity-75 active:opacity-100">
                            <FontAwesomeIcon :icon="faSearch" />
                        </div>
                    </template>
                </PureInput>
                </div>
            </template>

            <template #option="{ option, selected }">
                <div class="flex flex-wrap p-1 items-center gap-4 w-full">
                    <Avatar :icon="option.state" class="mr-2" size="small">
                        <template #icon>
                            <Icon :data="option.state_icon"></Icon>
                        </template>
                    </Avatar>
                    <div class="flex-1 flex flex-col">
                        <span class="font-medium text-sm">{{ option.name }}</span>
                        <span
                            :class="['text-sm', { 'text-surface-500 dark:text-surface-400': !selected, 'text-inherit': selected }]">{{
                            option.email }}</span>
                    </div>
                    <span class="font-bold">{{ useFormatTime(option.last_contacted_at) }}</span>
                </div>
            </template>
        </PickList>
    </div>


</template>

<style lang="scss" scoped>
:deep(.p-listbox .p-listbox-list .p-listbox-option.p-listbox-option-selected) {
    background: rgb(203 213 225);
    color: black;
}
</style>
