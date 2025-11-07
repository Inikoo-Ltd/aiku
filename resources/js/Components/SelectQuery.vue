<script setup lang="ts">
import Multiselect from "@vueform/multiselect"

import { ref, onMounted, onUnmounted, inject } from 'vue'
import axios from "axios"
import { notify } from "@kyvg/vue3-notification"
import Tag from '@/Components/Tag.vue'


import { faChevronDown } from '@fas'
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { trans } from "laravel-vue-i18n"
import LoadingIcon from "./Utils/LoadingIcon.vue"
import LoadingText from "./Utils/LoadingText.vue"
import { layoutStructure } from "@/Composables/useLayoutStructure"

library.add(faChevronDown)

const props = withDefaults(defineProps<{
    fieldName?: string
    urlRoute: string
    placeholder?: string
    required?: boolean
    mode?: "single" | "multiple" | "tags" | undefined
    searchable?: boolean
    label?: string
    valueProp?: string
    closeOnSelect?: boolean
    clearOnSearch?: boolean
    object?: boolean
    value: any
    onChange?: Function
    canClear?: boolean
    filterOptions? : Function
    caret?: boolean
    trackBy?: string
    closeOnDeselect?: boolean
    createOption?: boolean
    onCreate?: any
    isSelected?: Function
    loadingCaret?:boolean
    disabled?: boolean
    noFetchOnMounted?: boolean
    prefixQuery?: string   // from filter[global] to stored_items_filter[global]
    initOptions?: {}[]
}>(), {
    required: false,
    placeholder: 'select',
    mode: 'single',
    searchable: true,
    valueProp: 'id',
    label: 'name',
    caret: true,
    closeOnSelect: false,
    clearOnSearch: true,
    object: false,
    value: null,
    fieldName: '',
    createOption: false,
    onChange: () => null,
    canClear: false,
    loadingCaret : false,
    disabled : false

})

const emits = defineEmits<{
    (e: 'updateVModel'): void
    (e: 'open', value : any): void
    (e: 'filerOption', value : String): void
}>()

const layout = inject('layout', layoutStructure)

let timeoutId: any
const optionData = ref(props.initOptions || [])
const q = ref('')
const page = ref(1)
const loading = ref(false)
const _multiselectRef = ref()
const lastPage = ref(2)


// Method: retrieve locations list
const getOptions = async () => {
    const filterQuery = props.prefixQuery ? `${props.prefixQuery}_filter[global]` : 'filter[global]'
    console.log(props.urlRoute)
    loading.value = true
    try {
        const response = await axios.get(props.urlRoute, {
            params: {
                [filterQuery]: q.value,
                page: page.value,
                  
            }
        })
        await onGetOptionsSuccess(response)
        loading.value = false
    } catch (error) {
        console.log(error)
        loading.value = false
        notify({
            title: "Failed",
            text: "Error while fetching data",
            type: "error"
        })
    }
}


const onGetOptionsSuccess = async (response) => {
    const newData = response?.data?.data ?? [];
    const updatedOptions = q.value && q.value !== '' ? [...newData] : page.value > 1 ? [...optionData.value, ...newData] : [...newData];
    optionData.value = props.filterOptions ? props.filterOptions(updatedOptions) : updatedOptions;
    lastPage.value = response?.data?.meta?.last_page ?? lastPage.value;
}



const SearchChange = (value: any) => {
    if(value === null) return // ==========================
    q.value = value
    page.value = 1
    clearTimeout(timeoutId)
    timeoutId = setTimeout(() => {
        getOptions()
    }, 500)
}


const onScrollMultiselect = () => {
    const dropdown = document.querySelector('.multiselect-dropdown')
    if (!dropdown) return

    // Detect when user has scrolled to the bottom of the dropdown
    const bottomReached = dropdown.scrollTop + dropdown.clientHeight >= dropdown.scrollHeight
    if (bottomReached) {
        // Load more data when bottom is reached
        page.value++
        if (page.value < lastPage.value)
            getOptions()
    }
}

const onCreate = (option, select) => {
    return props.onCreate(option, select).then(async (create) => {
        props.value[props.fieldName] = create; // Assign the result to the specified field
        await getOptions(); // Call getOptions after the value is set
        return create // Return false as specified
    }).catch(error => {
        console.error('Error in onCreate:', error);
        // Handle the error as needed
    });
};




onMounted(() => {
    // If not selected yet, then auto focus the Multiselect
    /*  if(!props.value.id) {
         _multiselectRef.value?.open()
         document.querySelector('.multiselect-search')?.focus()
     } */
    const dropdown = document.querySelector('.multiselect-dropdown')
    if (dropdown) {
        dropdown.addEventListener('scroll', onScrollMultiselect)
    }

    if (props.noFetchOnMounted) {
    } else {
        getOptions()
    }

})




onUnmounted(() => {
    const dropdown = document.querySelector('.multiselect-dropdown')
    if (dropdown) {
        dropdown.removeEventListener('scroll', onScrollMultiselect)
    }
})



defineExpose({
    _multiselectRef,
    optionData,
    q,
    page,
})

</script>

<template>
    <Multiselect
        ref="_multiselectRef"
        v-model="value[fieldName]"
        @update:modelValue="emits('updateVModel')"
        :placeholder="props.placeholder"
        :trackBy="props.trackBy"
        :label="props.label"
        :valueProp="props.valueProp"
        :object="props.object"
        :clearOnSearch="props.clearOnSearch"
        :close-on-select="props.closeOnSelect"
        :disabled="disabled"
        :searchable="props.searchable"
        :caret="disabled ? false : props.caret"
        :canClear="props.canClear"
        :options="optionData"
        :mode="props.mode"
        :appendNewOption="false"
        :on-create="onCreate"
        :create-option="props.createOption"
        :noResultsText="loading ? 'loading...' : 'No Result'"
        @open="getOptions()"
        @search-change="SearchChange"
        @change="((e)=>{props.onChange(e), getOptions()})"
        :closeOnDeselect="closeOnDeselect"
        :isSelected="isSelected"
        :loading="loadingCaret || loading"
    >
        
        <template #tag="{ option, handleTagRemove, disabled }">
            <slot name="tag" :option="option" :handleTagRemove="handleTagRemove" :disabled="disabled">
                <div class="px-0.5 py-[3px]">
                    <Tag :theme="option[props.valueProp]" :label="option[props.label]" :closeButton="true"
                        :stringToColor="true" size="sm" @onClose="(event) => handleTagRemove(option, event)" />
                </div>
            </slot>
        </template>

        <template #placeholder>
            <slot name="placeholder" :search="q" >
                <div v-if="loading" class="flex items-center gap-x-1 text-gray-400 w-full px-2 py-2" >
                    <LoadingIcon />
                    <LoadingText />
                </div>
            </slot>
        </template>

        <template #noresults>
            <slot name="noresults" :search="q" >
                <div class="px-2 py-2" >
                    {{ trans("No Result") }}
                </div>
            </slot>
        </template>
        
        <template #option="{option, isSelected, isPointed, search}">
            <slot name="option" :search="q" :option :isSelected :isPointed :label="props.label">
            </slot>
        </template>
        
        <template #nooptions>
            <slot name="nooptions" :search="q" >
                <div class="px-2 py-2" >
                    {{ trans("No Result") }}
                </div>
            </slot>
        </template>
        
        <template #afterlist="{ options }">
            <slot name="afterlist" :search="q" :options >
            </slot>
        </template>

        <template #caret="{handleCaretClick, isOpen}" >
            <slot name="caret" :handleCaretClick="handleCaretClick" :isOpen="isOpen" >
                <div class="px-2" >
                    <font-awesome-icon :icon="['fas', 'chevron-down']" class="text-xs mr-2" />
                </div>
            </slot>
        </template>

        <template #singlelabel="{value}" >
            <slot name="singlelabel" :value="value" >
                <div class="flex justify-start w-full px-2 z-50">
                    {{ value[props.label] }}
                </div>
            </slot>
        </template>

    </Multiselect>
</template>

<style scoped>
:deep(.multiselect-single-label) {
    padding-right: calc(1.5rem + var(--ms-px, .035rem)*3) !important;
}

:deep(.multiselect-search) {
    background: transparent !important;
}

/* For Multiselect */
:deep(.multiselect-option.is-selected),
:deep(.multiselect-option.is-selected.is-pointed) {
    background-color: v-bind('layout?.app?.theme[4]') !important;
    color: v-bind('layout?.app?.theme[5]') !important;
}

:deep(.multiselect-option.is-pointed) {
	background-color: v-bind('layout?.app?.theme[4] + "15"') !important;
    color: v-bind('`color-mix(in srgb, ${layout?.app?.theme[4]} 50%, black)`') !important;
}

:deep(.multiselect-option.is-disabled) {
	@apply bg-gray-300 text-gray-500 !important;
}

:deep(.multiselect.is-active) {
	border: var(--ms-border-width-active, var(--ms-border-width, 1px)) solid
		var(--ms-border-color-active, var(--ms-border-color, #787878)) !important;
	box-shadow: 0 0 0 var(--ms-ring-width, 3px) var(--ms-ring-color, rgba(42, 42, 42, 0.188)) !important;
	/* box-shadow: 4px 0 0 0 calc(4px + 4px) rgba(42, 42, 42, 1); */
}

:deep(.multiselect-dropdown) {
    max-height: 250px !important;
}
:deep(.multiselect-tags-search) {
    @apply focus:outline-hidden focus:ring-0
}

:deep(.multiselect-tags) {
    @apply m-0.5
}

:deep(.multiselect-tag-remove-icon) {
    @apply text-lime-800
}
</style>