<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import {
    Combobox,
    ComboboxInput,
    ComboboxButton,
    ComboboxOptions,
    ComboboxOption,
    TransitionRoot,
} from '@headlessui/vue'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faChevronDown, faCheck } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faChevronDown, faCheck)

defineEmits(['update:modelValue'])
const props = defineProps<{
    form: any
    modelValue: Object
    loading: Boolean
    apiUrl: String
    fieldData: any
    fieldName: string
}>()
console.log(props.fieldData)
const isLoading = ref(true)
const comboValue = ref('Select Users')
const optionsResult = ref()
const query = ref('')

const fetchApi = async (query: string) => {
    if (query !== '') {
        await fetch(props.fieldData.apiUrl + query)
            .then(response => {
                response.json().then((data: Object) => {
                    isLoading.value = false
                    optionsResult.value = data.data
                })
            })
            .catch(err => console.log(err))
    }
    else {
        comboValue.value = 'Select Users'
    }
}



watch(query, (q) => {
    fetchApi(q)
}, { immediate: true })

let filteredOptions = computed(() =>
    query.value === ''
        ? optionsResult.value
        : optionsResult.value.filter((person) =>
            person.contact_name
                .toLowerCase()
                .replace(/\s+/g, '')
                .includes(query.value.toLowerCase().replace(/\s+/g, ''))
        )
)

</script>

<template>
    <Combobox by="value" v-model="form[fieldName]">
        <div class="relative mt-1">
            <div
                class="relative w-full cursor-default overflow-hidden rounded-lg bg-white text-left border border-gray-300 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-indigo-700/75 focus-visible:ring-offset-2 focus-visible:ring-offset-teal-300 sm:text-sm">
                <ComboboxInput class="w-full border-none py-2 pl-3 pr-10 text-sm leading-5 text-gray-900 focus:ring-0"
                    :displayValue="(abcde) => abcde.slug" @change="query = $event.target.value" />
                <ComboboxButton class="absolute inset-y-0 right-0 flex items-center pr-2">
                    <FontAwesomeIcon icon="fas fa-chevron-down" class="h-4 w-4 text-gray-400" aria-hidden="true" />
                </ComboboxButton>
            </div>

            <!-- Popup: List -->
            <TransitionRoot leave="transition ease-in duration-100" leaveFrom="opacity-100" leaveTo="opacity-0"
                @after-leave="query = ''">
                <ComboboxOptions
                    class="z-10 absolute mt-1 max-h-60 w-full overflow-auto rounded-md bg-white py-1 text-base shadow-lg ring-1 ring-black/5 focus:outline-hidden sm:text-sm">
                    <div v-if="query == '' && optionsResult?.length == 0" class="py-2 pl-4 pr-4">Query is empty</div>
                    <div v-if="query != '' && optionsResult?.length == 0" class="py-2 pl-4 pr-4">No result found</div>
                    <!-- State: Loading and Not Found -->
                    <!-- <div v-if="isLoading" class="relative cursor-default select-none py-2 px-4 text-gray-700">
                        Loading...
                    </div>
                    <div v-if="filteredOptions.length === 0 && !isLoading"
                        class="relative cursor-default select-none py-2 px-4 text-gray-700">
                        Nothing found.
                    </div> -->

                    <!-- List -->
                    <template v-if="!isLoading">
                        <ComboboxOption v-for="option in optionsResult" as="template" :key="option.id"
                            :value="option.contact_name ?? option" v-slot="{ selected, active }">
                            <li class="relative cursor-pointer select-none py-2 pl-10 pr-4" :class="{
                                'bg-indigo-600 text-white': active,
                                'text-gray-800': !active,
                            }">
                                <span class="block truncate" :class="{ 'font-medium': selected, 'font-normal': !selected }">
                                    {{ option.contact_name ?? option.slug }}
                                </span>
                                <span v-if="selected" class="absolute inset-y-0 left-0 flex items-center pl-3"
                                    :class="{ 'text-white': active, 'text-teal-600': !active }">
                                    <FontAwesomeIcon icon="fas fa-check" class="h-4 w-4 text-indigo-600"
                                        aria-hidden="true" />
                                </span>
                            </li>
                        </ComboboxOption>
                    </template>
                </ComboboxOptions>
            </TransitionRoot>
        </div>
    </Combobox>
</template>
