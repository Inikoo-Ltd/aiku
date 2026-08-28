<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { trans } from "laravel-vue-i18n"
import { route } from "ziggy-js"
import Popover from "primevue/popover"
import Checkbox from "primevue/checkbox"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faDownload, faSitemap } from "@fal"
import { routeType } from "@/types/route"

library.add(faSitemap)

const props = defineProps<{
    fields: { key: string; label: string }[]
    downloadRoute: { xlsx: routeType; csv: routeType }
    states?: { key: string; label: string }[]
    label?: string
    tooltip?: string
    icon?: string | string[]
}>()

const exportPanel = ref()
const selectedColumns = ref<string[]>([])
const selectedStates = ref<string[]>([])

const allColumnsSelected = computed({
    get: () => !!props.fields.length && selectedColumns.value.length === props.fields.length,
    set: (value: boolean) => {
        selectedColumns.value = value ? props.fields.map(field => field.key) : []
    }
})

watch(() => props.fields, (fields) => {
    selectedColumns.value = fields.map(field => field.key)
}, { immediate: true })

watch(() => props.states, (states) => {
    selectedStates.value = (states ?? []).map(state => state.key)
}, { immediate: true })

const canExport = computed(() => !!selectedColumns.value.length && (!props.states?.length || !!selectedStates.value.length))

const onExport = (type: 'csv' | 'xlsx') => {
    const exportRoute = props.downloadRoute?.[type]
    if (!exportRoute?.name || !canExport.value) return

    const base = route(exportRoute.name, exportRoute.parameters) as unknown as string

    const query = new URLSearchParams()
    selectedColumns.value.forEach(column => query.append('columns[]', column))
    selectedStates.value.forEach(state => query.append('states[]', state))

    window.open(base + (base.includes('?') ? '&' : '?') + query.toString(), '_blank')
}
</script>

<template>
    <div v-if="fields.length">
        <Button :icon="icon ?? faSitemap" :label="label ?? trans('Export Structure')" :tooltip="tooltip"
            type="tertiary" @click="exportPanel.toggle($event)" />

        <Popover ref="exportPanel">
            <div class="w-72">
                <div class="text-sm font-medium">{{ trans("Website structure export") }}</div>
                <div class="text-xs text-gray-500 mb-2">
                    {{ trans("Department, sub departments, families and collections of this department, with the columns you pick below") }}
                </div>

                <div class="flex items-center gap-2 pb-2 mb-2 border-b border-gray-200">
                    <Button :icon="faDownload" label="XLSX" type="tertiary"
                        :disabled="!canExport" @click="onExport('xlsx')" />
                    <Button :icon="faDownload" label="CSV" type="tertiary"
                        :disabled="!canExport" @click="onExport('csv')" />
                </div>

                <label class="flex items-center gap-2 px-1 py-1.5 font-medium cursor-pointer select-none">
                    <Checkbox v-model="allColumnsSelected" :binary="true" />
                    <span>{{ trans("Select all columns") }}</span>
                </label>

                <div class="max-h-72 overflow-y-auto">
                    <label v-for="field in fields" :key="field.key"
                        class="flex items-center gap-2 px-1 py-1.5 cursor-pointer select-none hover:bg-gray-50 rounded">
                        <Checkbox v-model="selectedColumns" :value="field.key" />
                        <span>{{ field.label }}</span>
                    </label>

                    <template v-if="states?.length">
                        <div class="mt-2 pt-2 border-t border-gray-200 text-sm font-medium">
                            {{ trans("State") }}
                        </div>

                        <label v-for="state in states" :key="state.key"
                            class="flex items-center gap-2 px-1 py-1.5 cursor-pointer select-none hover:bg-gray-50 rounded">
                            <Checkbox v-model="selectedStates" :value="state.key" />
                            <span>{{ state.label }}</span>
                        </label>
                    </template>
                </div>
            </div>
        </Popover>
    </div>
</template>
