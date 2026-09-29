<script setup lang="ts">
import { computed, ref } from 'vue'
import axios from 'axios'
import { debounce } from 'lodash-es'
import Select from 'primevue/select'
import { notify } from '@kyvg/vue3-notification'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import { routeType } from '@/types/route'
import { ctrans } from '@/Composables/useTrans'

type Option = Record<string, any>

const model = defineModel<any>()

const props = withDefaults(defineProps<{
    fetchRoute: routeType
    valueProp?: string
    labelProp?: string
    labelAdditionalProp?: string
    object?: boolean
    placeholder?: string
    noOptionsText?: string
    fetchOnOpen?: boolean
    required?: boolean
    size?: 'small' | 'large'
}>(), {
    valueProp: 'id',
    labelProp: 'name',
    labelAdditionalProp: 'code',
    fetchOnOpen: true,
})

const emits = defineEmits<{
    (e: 'selectedObject', value: Option | null): void
}>()

const fetchedOptions = ref<Option[]>([])
const nextPageUrl = ref<string | null>(null)
const isLoading = ref(false)
const search = ref('')
const selectedOption = ref<Option | null>(props.object && model.value ? model.value : null)
let fetchSequence = 0

const options = computed(() => {
    const selected = selectedOption.value
    if (!selected || fetchedOptions.value.some(option => option[props.valueProp] === selected[props.valueProp])) {
        return fetchedOptions.value
    }

    return [selected, ...fetchedOptions.value]
})

const firstPageUrl = () => route(props.fetchRoute.name, {
    ...props.fetchRoute.parameters,
    'filter[global]': search.value,
})

const fetchOptions = async (url: string, replace: boolean) => {
    const sequence = ++fetchSequence
    isLoading.value = true

    try {
        const response = await axios.get(url)
        if (sequence !== fetchSequence) return

        const page: Option[] = response.data?.data ?? response.data ?? []
        fetchedOptions.value = replace ? page : [...fetchedOptions.value, ...page]
        nextPageUrl.value = response.data?.links?.next ?? null
    } catch {
        if (sequence !== fetchSequence) return
        notify({ title: ctrans('Something went wrong'), text: ctrans('Failed to get the options list'), type: 'error' })
    } finally {
        if (sequence === fetchSequence) {
            isLoading.value = false
        }
    }
}

const onShow = () => {
    if (props.fetchOnOpen && !fetchedOptions.value.length && !isLoading.value) {
        fetchOptions(firstPageUrl(), true)
    }
}

const onFilter = debounce((event: { value: string }) => {
    search.value = event.value ?? ''
    fetchOptions(firstPageUrl(), true)
}, 400)

const onListScroll = (event: Event) => {
    const list = event.target as HTMLElement
    const bottomReached = list.scrollTop + list.clientHeight >= list.scrollHeight - 10

    if (bottomReached && nextPageUrl.value && !isLoading.value) {
        fetchOptions(nextPageUrl.value, false)
    }
}

const onChange = (value: any) => {
    const option = value === null || value === undefined
        ? null
        : props.object ? value : options.value.find(item => item[props.valueProp] === value) ?? null

    selectedOption.value = option
    emits('selectedObject', option)
}

const labelOf = (option: Option) => option?.[props.labelProp]
const additionalLabelOf = (option: Option) => option?.[props.labelAdditionalProp]
</script>

<template>
    <Select
        v-model="model"
        :options="options"
        :optionLabel="labelProp"
        :optionValue="object ? undefined : valueProp"
        :dataKey="valueProp"
        :filterFields="[labelProp, labelAdditionalProp]"
        :placeholder="placeholder"
        :loading="isLoading"
        :showClear="!required"
        :size="size"
        :pt="{ listContainer: { onScroll: onListScroll } }"
        filter
        autoFilterFocus
        resetFilterOnHide
        class="w-full"
        @show="onShow"
        @filter="onFilter"
        @update:modelValue="onChange">
        <template #value="{ value, placeholder: emptyLabel }">
            <span v-if="value !== null && value !== undefined && selectedOption" class="block truncate">
                {{ labelOf(selectedOption) }}
                <span v-if="additionalLabelOf(selectedOption)" class="text-gray-400">({{ additionalLabelOf(selectedOption) }})</span>
            </span>
            <span v-else class="text-gray-400">{{ emptyLabel }}</span>
        </template>

        <template #option="{ option }">
            <span class="truncate">
                {{ labelOf(option) }}
                <span v-if="additionalLabelOf(option)" class="text-sm text-gray-400">({{ additionalLabelOf(option) }})</span>
            </span>
        </template>

        <template #emptyfilter>
            <span v-if="!isLoading">{{ noOptionsText ?? ctrans('No options') }}</span>
        </template>

        <template #empty>
            <span v-if="!isLoading">{{ noOptionsText ?? ctrans('No options') }}</span>
        </template>

        <template #footer>
            <div v-if="isLoading" class="flex justify-center py-2 text-lg">
                <LoadingIcon />
            </div>
        </template>
    </Select>
</template>
