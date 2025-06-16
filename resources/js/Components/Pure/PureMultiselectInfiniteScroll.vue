<script setup lang='ts'>
import Multiselect from "@vueform/multiselect"
import { debounce } from 'lodash-es'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'

import PureInputNumber from '@/Components/Pure/PureInputNumber.vue'
import { Links, Meta, Table } from '@/types/Table'
import { inject, onMounted, onUnmounted, ref } from "vue"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"
import axios from "axios"
import { router } from '@inertiajs/vue3'
import { routeType } from "@/types/route"

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faChevronLeft, faChevronRight } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { layoutStructure } from "@/Composables/useLayoutStructure"
library.add(faChevronLeft, faChevronRight)

const model = defineModel()
const props = defineProps<{
    mode?: "single" | "multiple" | "tags"
    classes?: {}
    fetchRoute: routeType
    required?: boolean
    isLoading?: boolean
    placeholder?: string
    labelProp?: string
    noOptionsText?: string
    initOptions?: {}[]
    valueProp?: string
    object?: boolean
}>()
const emits = defineEmits<{
    (e: 'optionsList', value: any[]): void
}>()

const layout = inject('layout', layoutStructure)


const isComponentLoading = ref<string | boolean>(false)

const getUrlFetch = (additionalParams: {}) => {
    return route(
        props.fetchRoute.name,
        {
            ...props.fetchRoute.parameters,
            ...additionalParams
        }
    )
}

const optionsList = ref<any[]>([])
const optionsMeta = ref<Meta | null>(null)
const optionsLinks = ref<Links | null>(null)
const fetchProductList = async (url?: string) => {
    isComponentLoading.value = 'fetchProduct'

    const urlToFetch = url || route(props.fetchRoute.name, props.fetchRoute.parameters)

    try {
        const xxx = await axios.get(urlToFetch)
        
        optionsList.value = [...optionsList.value, ...xxx?.data?.data]
        optionsMeta.value = xxx?.data.meta || null
        optionsLinks.value = xxx?.data.links || null

        console.log('fetch', optionsList.value)

        emits('optionsList', optionsList.value)
    } catch (error) {
        // console.log(error)
        notify({
            title: trans('Something went wrong.'),
            text: trans('Failed to fetch product list'),
            type: 'error',
        })
    }
    isComponentLoading.value = false
}
    
const onSearchQuery = debounce(async (query: string) => {
    optionsList.value = []
    fetchProductList(getUrlFetch({'filter[global]': query}))
}, 500)


// Method: fetching next page
const onFetchNext = () => {
    const dropdown = document.querySelector('.multiselect-dropdown')
    // console.log(dropdown?.scrollTop, dropdown?.clientHeight, dropdown?.scrollHeight)

    const bottomReached = (dropdown?.scrollTop || 0) + (dropdown?.clientHeight || 0) >= (dropdown?.scrollHeight || 10) - 10
    if (bottomReached && optionsLinks.value?.next && isComponentLoading.value != 'fetchProduct') {
        // console.log(dropdown?.scrollTop, dropdown?.clientHeight, dropdown?.scrollHeight)
        fetchProductList(optionsLinks.value.next)
    }
}

onMounted(() => {
    const dropdown = document.querySelector('.multiselect-dropdown')
    // console.log('bb', dropdown, dropdown?.scrollTop)
    if (dropdown) {
        dropdown.addEventListener('scroll', onFetchNext)
    }
})

onUnmounted(() => {
    const dropdown = document.querySelector('.multiselect-dropdown')
    if (dropdown) {
        dropdown.removeEventListener('scroll', onFetchNext)
    }
})


const _multiselectRef = ref()

</script>

<template>
    <!-- <pre>{{ options }}</pre> -->
    <!-- <div class="relative w-full text-gray-600 rounded-sm"> -->
        <Multiselect
            ref="_multiselectRef"
            v-model="model"
            :options="optionsList.length ? optionsList : (initOptions || [])"
            :classes="{
                placeholder: 'pointer-events-none absolute top-1/2 z-10 -translate-y-1/2 select-none text-sm text-left w-full pl-4 font-light text-gray-400 opacity-1',
                ...classes,
            }"
            :valueProp="valueProp ?? 'id'"
            :filterResults="false"
            :object
            @change="(e) => _multiselectRef?.clearSearch()"
            :canClear="!required"
            :mode="mode || 'single'"
            :closeOnSelect="mode == 'multiple' ? false : true"
            :canDeselect="!required"
            :hideSelected="false"
            :clearOnSelect="false"
            searchable
            :clearOnBlur="false"
            clearOnSearch
            autofocus
            :caret="isComponentLoading ? false : true"
            :loading="isLoading || isComponentLoading === 'fetchProduct'"
            :placeholder="placeholder || trans('Select option')"
            :resolve-on-load="true"
            :min-chars="1"
            @open="() => optionsList?.length ? false : fetchProductList()"
            @search-change="(ee) => onSearchQuery(ee)"
        >

            <template #singlelabel="{ value }">
            <!-- {{ $attrs }} -->
                <slot name="singlelabel" :value>
                    <div class="w-full text-left pl-4">{{ value[labelProp || 'name'] }} <span class="text-sm text-gray-400">({{ value.code }})</span></div>
                </slot>
            </template>

            <template #option="{ option, isSelected, isPointed }">
                <slot name="option" :option :isSelected :isPointed>
                    <div class="">{{ option[labelProp || 'name'] }} <span class="text-sm" :class="isSelected(option) ? 'text-indigo-200' : 'text-gray-400'">({{ option.code }})</span></div>
                </slot>
            </template>

            <template #spinner>
                <LoadingIcon class="mr-3" />
                <!-- <div /> -->
            </template>

            <!-- <template #noresults>
                xxxxxxxx
            </template> -->

            <template #nooptions>
                <div v-if="isComponentLoading !== 'fetchProduct'" class="py-2 px-3 text-gray-600 bg-white text-left rtl:text-right">
                    {{ noOptionsText || trans('No options')}}
                </div>
                <div></div>
            </template>

            <template #afterlist>
                <div v-if="isComponentLoading === 'fetchProduct'" class="py-2 flex justify-center text-xl">
                    <LoadingIcon />
                </div>
                <slot name="afterlist" />
            </template>
        </Multiselect>
    <!-- </div> -->
</template>

<style src="@vueform/multiselect/themes/default.css"></style>

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
    @apply focus:outline-none focus:ring-0
}

:deep(.multiselect-tags) {
    @apply m-0.5
}

:deep(.multiselect-tag-remove-icon) {
    @apply text-lime-800
}
</style>