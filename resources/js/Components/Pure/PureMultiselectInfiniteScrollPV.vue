<script setup lang='ts'>
import { Select } from 'primevue'
import { debounce } from 'lodash-es'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'

import { Links, Meta } from '@/types/Table'
import { inject, nextTick, ref } from "vue"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"
import axios from "axios"
import { routeType } from "@/types/route"
import { layoutStructure } from "@/Composables/useLayoutStructure"

const model = defineModel()
const props = defineProps<{
    mode?: "single" | "multiple" | "tags"
    classes?: {}
    fetchRoute: routeType
    required?: boolean
    isLoading?: boolean
    placeholder?: string
    labelProp?: string
    labelAdditionalProp?: string
    noOptionsText?: string
    initOptions?: {}[]
    valueProp?: string
    object?: boolean
    clearOnSelect?: boolean
    clearOnBlur?: boolean
    clearOnFocus?: boolean
    optionFunc?: (item: any) => boolean
}>()

const emits = defineEmits<{
    (e: 'optionsList', value: any[]): void
    (e: 'selectedObject', value: any[]): void
}>()

const layout = inject('layout', layoutStructure)
const isComponentLoading = ref<string | boolean>(false)

const getUrlFetch = (additionalParams: {}) => {
    return route(props.fetchRoute.name, {
        ...props.fetchRoute.parameters,
        ...additionalParams
    })
}

const optionsList = ref<any[]>([])
const optionsMeta = ref<Meta | null>(null)
const optionsLinks = ref<Links | null>(null)

const fetchProductList = async (url?: string) => {
    isComponentLoading.value = 'fetchProduct'
    const urlToFetch = url || route(props.fetchRoute.name, props.fetchRoute.parameters)

    try {
        const res = await axios.get(urlToFetch)

        if (res?.data?.data) {
            const raw = res.data.data
            const filtered = typeof props.optionFunc === 'function'
                ? raw.filter((item: any) => props.optionFunc!(item))
                : raw
            optionsList.value = [...optionsList.value, ...filtered]
            optionsMeta.value = res?.data?.meta || null
            optionsLinks.value = res?.data?.links || null
        } else {
            optionsList.value = res?.data
        }

        emits('optionsList', optionsList.value)
    } catch (error) {
        notify({
            title: trans('Something went wrong.'),
            text: trans('Failed to get the options list'),
            type: 'error',
        })
    }

    isComponentLoading.value = false
}

const onSearchQuery = debounce(async (query: string) => {
    optionsList.value = []
    fetchProductList(getUrlFetch({ 'filter[global]': query }))
}, 500)

// Infinite scroll: attach to PrimeVue panel list container after show
let listContainerEl: HTMLElement | null = null

const onFetchNext = () => {
    if (!listContainerEl) return
    const { scrollTop, clientHeight, scrollHeight } = listContainerEl
    if (scrollTop + clientHeight >= scrollHeight - 10 && optionsLinks.value?.next && isComponentLoading.value !== 'fetchProduct') {
        fetchProductList(optionsLinks.value.next)
    }
}

const searchInputRef = ref<HTMLInputElement | null>(null)
const searchQuery = ref('')

const onSearchInput = (value: string) => {
    searchQuery.value = value
    if (value) {
        onSearchQuery(value)
    } else {
        onSearchQuery.cancel()
        optionsList.value = []
        fetchProductList(getUrlFetch({ 'filter[global]': '' }))
    }
}

const onShow = () => {
    optionsList.value = []
    searchQuery.value = ''
    fetchProductList(getUrlFetch({ 'filter[global]': '' }))

    nextTick(() => {
        setTimeout(() => {
            const containers = document.querySelectorAll<HTMLElement>('.p-select-list-container')
            listContainerEl = containers[containers.length - 1] ?? null
            listContainerEl?.addEventListener('scroll', onFetchNext)
            searchInputRef.value?.focus()
        }, 50)
    })
}

const onHide = () => {
    listContainerEl?.removeEventListener('scroll', onFetchNext)
    listContainerEl = null
    onSearchQuery.cancel()
}

defineExpose({
    fetchProductList,
    onSearchQuery,
    optionsList
})
</script>

<template>
    <Select
        v-model="model"
        :options="optionsList.length ? optionsList : (initOptions || [])"
        :optionLabel="labelProp || 'name'"
        :optionValue="!object ? (valueProp || 'id') : undefined"
        :placeholder="placeholder || trans('Select option')"
        :loading="isLoading || isComponentLoading === 'fetchProduct'"
        :showClear="!required"
        class="w-full"
        @show="onShow"
        @hide="onHide"
        @change="(e) => emits('selectedObject', e.value)"
    >
        <template #header>
            <div class="p-2 pb-1">
                <input
                    ref="searchInputRef"
                    v-model="searchQuery"
                    type="text"
                    class="w-full px-3 py-1.5 text-sm border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-gray-400"
                    :placeholder="trans('Search...')"
                    @input="(e) => onSearchInput((e.target as HTMLInputElement).value)"
                    @keydown.stop
                    @click.stop
                />
            </div>
        </template>

        <template #option="{ option }">
            <slot name="option" :option>
                <div>
                    {{ option[labelProp || 'name'] }}
                    <span v-if="labelAdditionalProp || option.code" class="text-sm text-gray-400">
                        ({{ option[labelAdditionalProp || 'code'] }})
                    </span>
                </div>
            </slot>
        </template>

        <template #value="slotProps">
            <slot name="singlelabel" :value="slotProps.value">
                <span v-if="slotProps.value" class="truncate">
                    {{ slotProps.value[labelProp || 'name'] }}
                    <span v-if="labelAdditionalProp || slotProps.value?.code" class="text-sm text-gray-400">
                        ({{ slotProps.value[labelAdditionalProp || 'code'] }})
                    </span>
                </span>
            </slot>
        </template>

        <template #footer>
            <div v-if="isComponentLoading === 'fetchProduct'" class="py-2 flex justify-center">
                <LoadingIcon />
            </div>
        </template>

        <template #empty>
            <div v-if="isComponentLoading !== 'fetchProduct'" class="py-2 px-3 text-sm text-gray-600">
                {{ noOptionsText || trans('No options') }}
            </div>
        </template>
    </Select>
</template>
