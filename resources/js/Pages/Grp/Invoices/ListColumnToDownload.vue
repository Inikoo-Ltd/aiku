<script setup lang="ts">
import Button from '@/Components/Elements/Buttons/Button.vue'
import { routeType } from '@/types/route'
import { Checkbox } from 'primevue'
import { computed, watch } from 'vue'
import { ref } from 'vue'
import axios from 'axios'
import { debounce } from 'lodash-es'

const props = defineProps<{
    routeDownload: routeType | null
    listColumn: {
        label: string
        is_checked: boolean
        value: string
    }[]
}>()

const selectedCheck = ref<string[]>(props.listColumn.map(check => check.is_checked ? check.value : null).filter(Boolean) as string[])

const saveSettings = debounce((selected: string[]) => {
    const columnSettings = props.listColumn.reduce<Record<string, boolean>>((acc, col) => {
        acc[col.value] = selected.includes(col.value)
        return acc
    }, {})

    axios.patch(route('grp.models.profile.update'), {
        settings: {
            download_pdf_column: columnSettings,
        },
    })
}, 800)

watch(selectedCheck, (newVal) => {
    saveSettings(newVal)
}, { deep: true })

const compSelectedDeck = computed(() => {
    const xxx = selectedCheck.value?.reduce((acc, curr) => {
        acc[curr] = true;
        return acc;
    }, {})

    if (!props.routeDownload) return '#'

    return route(props.routeDownload?.name, { ...props.routeDownload?.parameters, ...xxx })
})
</script>

<template>
    <div class="isolate bg-white px-6 lg:px-8">
        <div class="mx-auto max-w-2xl text-center mb-4">
            <h2 class="text-lg font-bold tracking-tight sm:text-2xl">
                {{ ctrans("Proforma Invoice") }}
            </h2>
        </div>

        <div class="flex flex-col gap-2">
            <div>{{ ctrans("Select additional information to included:") }}</div>
            <div v-for="check of props.listColumn" :key="check.value" class="flex items-center gap-2">
                <Checkbox v-model="selectedCheck" :inputId="check.value" :name="check.value" :value="check.value" />
                <label :for="check.value" class="cursor-pointer">{{ check.label }}</label>
            </div>
        </div>

        <a :href="compSelectedDeck" target="_blank" rel="noopener noreferrer"
            class="w-full block mt-6">
            <Button full :label="ctrans('Download Proforma Invoice')" />
        </a>
    </div>
</template>
