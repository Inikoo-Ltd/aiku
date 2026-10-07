<script setup lang="ts">
import { computed, ref } from 'vue'
import Select from 'primevue/select'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faArrowUp, faArrowDown, faTimes } from '@fal'
import { ctrans } from '@/Composables/useTrans'

library.add(faArrowUp, faArrowDown, faTimes)

const props = defineProps<{
    form: any
    fieldName: string
    fieldData: {
        options: { id: number, name: string }[]
        placeholder?: string
    }
}>()

const selectedIds = computed<number[]>({
    get: () => props.form[props.fieldName] ?? [],
    set: (ids) => {
        props.form[props.fieldName] = ids
        props.form.clearErrors()
    },
})

const optionName = (id: number) => props.fieldData.options.find((option) => option.id === id)?.name ?? `#${id}`

const remainingOptions = computed(() => props.fieldData.options.filter((option) => !selectedIds.value.includes(option.id)))

const move = (index: number, offset: number) => {
    const ids = [...selectedIds.value]
    ;[ids[index], ids[index + offset]] = [ids[index + offset], ids[index]]
    selectedIds.value = ids
}

const remove = (index: number) => {
    selectedIds.value = selectedIds.value.filter((_, position) => position !== index)
}

const toAdd = ref<number | null>(null)
const add = (id: number | null) => {
    if (id !== null) {
        selectedIds.value = [...selectedIds.value, id]
    }
    toAdd.value = null
}

const errors = computed(() => Object.entries(props.form.errors)
    .filter(([key]) => key === props.fieldName || key.startsWith(`${props.fieldName}.`))
    .map(([, message]) => message))
</script>

<template>
    <div class="w-full max-w-md">
        <ol v-if="selectedIds.length" class="mb-2 divide-y divide-gray-200 rounded border border-gray-300">
            <li v-for="(id, index) in selectedIds" :key="id" class="flex items-center gap-2 px-3 py-1.5">
                <span class="w-5 tabular-nums text-gray-400">{{ index + 1 }}.</span>
                <span class="flex-1">{{ optionName(id) }}</span>
                <button type="button" :disabled="index === 0" @click="move(index, -1)" v-tooltip="ctrans('Move up')" class="px-1 text-gray-500 hover:text-gray-800 disabled:opacity-25">
                    <FontAwesomeIcon icon="fal fa-arrow-up" fixed-width aria-hidden="true" />
                </button>
                <button type="button" :disabled="index === selectedIds.length - 1" @click="move(index, 1)" v-tooltip="ctrans('Move down')" class="px-1 text-gray-500 hover:text-gray-800 disabled:opacity-25">
                    <FontAwesomeIcon icon="fal fa-arrow-down" fixed-width aria-hidden="true" />
                </button>
                <button type="button" @click="remove(index)" v-tooltip="ctrans('Remove')" class="px-1 text-red-400 hover:text-red-600">
                    <FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
                </button>
            </li>
        </ol>

        <Select
            v-if="remainingOptions.length"
            v-model="toAdd"
            :options="remainingOptions"
            optionLabel="name"
            optionValue="id"
            :placeholder="fieldData.placeholder ?? ctrans('Add')"
            class="w-full"
            @update:modelValue="add"
        />

        <p v-for="error in errors" :key="error" class="mt-1 text-sm text-red-600">{{ error }}</p>
    </div>
</template>
