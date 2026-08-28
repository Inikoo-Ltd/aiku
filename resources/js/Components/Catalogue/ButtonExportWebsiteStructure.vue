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
    label?: string
    tooltip?: string
    icon?: string | string[]
}>()

const exportPanel = ref()
const selectedColumns = ref<string[]>([])

const allColumnsSelected = computed({
    get: () => !!props.fields.length && selectedColumns.value.length === props.fields.length,
    set: (value: boolean) => {
        selectedColumns.value = value ? props.fields.map(field => field.key) : []
    }
})

watch(() => props.fields, (fields) => {
    selectedColumns.value = fields.map(field => field.key)
}, { immediate: true })

const onExport = (type: 'csv' | 'xlsx') => {
    const exportRoute = props.downloadRoute?.[type]
    if (!exportRoute?.name || !selectedColumns.value.length) return

    const base = route(exportRoute.name, exportRoute.parameters) as unknown as string

    const query = new URLSearchParams()
    selectedColumns.value.forEach(column => query.append('columns[]', column))

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
                        :disabled="!selectedColumns.length" @click="onExport('xlsx')" />
                    <Button :icon="faDownload" label="CSV" type="tertiary"
                        :disabled="!selectedColumns.length" @click="onExport('csv')" />
                </div>

                <label class="flex items-center gap-2 px-1 py-1.5 font-medium cursor-pointer select-none">
                    <Checkbox v-model="allColumnsSelected" :binary="true" />
                    <span>{{ trans("Select all") }}</span>
                </label>

                <div class="max-h-72 overflow-y-auto">
                    <label v-for="field in fields" :key="field.key"
                        class="flex items-center gap-2 px-1 py-1.5 cursor-pointer select-none hover:bg-gray-50 rounded">
                        <Checkbox v-model="selectedColumns" :value="field.key" />
                        <span>{{ field.label }}</span>
                    </label>
                </div>
            </div>
        </Popover>
    </div>
</template>
