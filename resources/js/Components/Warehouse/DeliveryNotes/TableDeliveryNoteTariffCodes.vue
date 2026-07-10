<script setup lang="ts">
import { ref, computed, inject } from "vue"
import { router } from "@inertiajs/vue3"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import Table from "@/Components/Table/Table.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { routeType } from "@/types/route"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faDownload, faGlobe } from "@fal"
import { faSkull } from "@fas"
import Popover from "primevue/popover"
import Checkbox from "primevue/checkbox"

library.add(faDownload, faGlobe, faSkull)

const props = defineProps<{
    data: object
    tab?: string
    tariffCodesExport?: {
        currency_code?: string
        fields: { key: string; label: string }[]
        download_route: { xlsx: routeType; csv: routeType }
    }
}>()

const locale = inject("locale", aikuLocaleStructure)
const currencyCode = computed(() => props.tariffCodesExport?.currency_code)

const exportPanel = ref()
const exportFields = computed(() => props.tariffCodesExport?.fields ?? [])
const selectedColumns = ref<string[]>(exportFields.value.map(f => f.key))

const allColumnsSelected = computed({
    get: () => !!exportFields.value.length && selectedColumns.value.length === exportFields.value.length,
    set: (value: boolean) => {
        selectedColumns.value = value ? exportFields.value.map(f => f.key) : []
    }
})

const exportUrl = (type: 'csv' | 'xlsx') => {
    const r = props.tariffCodesExport?.download_route?.[type]
    if (!r?.name) return ''

    const base = route(r.name, r.parameters) as unknown as string
    const query = new URLSearchParams()
    selectedColumns.value.forEach(column => query.append('columns[]', column))

    const q = query.toString()
    return q ? base + (base.includes('?') ? '&' : '?') + q : base
}

const exportColumns = (type: 'csv' | 'xlsx') => {
    if (!selectedColumns.value.length) return
    window.open(exportUrl(type), '_blank')
}
</script>

<template>
    <Table :resource="data" :name="tab">
        <template v-if="tariffCodesExport?.download_route" #add-on-button>
            <Button :icon="faDownload" :label="trans('Export')" type="tertiary" size="xs"
                @click="exportPanel.toggle($event)" />

            <Popover ref="exportPanel">
                <div class="w-72">
                    <div class="flex items-center gap-2 pb-2 mb-2 border-b border-gray-200">
                        <Button :icon="faDownload" label="XLSX" type="tertiary"
                            :disabled="!selectedColumns.length" @click="exportColumns('xlsx')" />
                        <Button :icon="faDownload" label="CSV" type="tertiary"
                            :disabled="!selectedColumns.length" @click="exportColumns('csv')" />
                    </div>

                    <label class="flex items-center gap-2 px-1 py-1.5 font-medium cursor-pointer select-none">
                        <Checkbox v-model="allColumnsSelected" :binary="true" />
                        <span>{{ trans("Select all") }}</span>
                    </label>

                    <div class="max-h-72 overflow-y-auto">
                        <label v-for="field in exportFields" :key="field.key"
                            class="flex items-center gap-2 px-1 py-1.5 cursor-pointer select-none hover:bg-gray-50 rounded">
                            <Checkbox v-model="selectedColumns" :value="field.key" />
                            <span>{{ field.label }}</span>
                        </label>
                    </div>
                </div>
            </Popover>
        </template>

        <template #cell(description)="{ item }">
            <span class="block max-w-xs text-xs text-gray-500">{{ item.description }}</span>
        </template>

        <template #cell(origin)="{ item }">
            <span v-if="item.origin" class="inline-flex items-center gap-1.5">
                <img :src="`/flags/${item.origin.toLowerCase()}.png`" :alt="item.origin"
                    v-tooltip="item.origin_name || item.origin"
                    class="h-3.5 w-5 object-cover rounded-sm" />
                {{ item.origin }}
            </span>
        </template>

        <template #cell(dg)="{ item }">
            <span>
                <FontAwesomeIcon v-if="item.dg" :icon="faSkull" class="text-red-500" :title="trans('Dangerous goods')" />
            </span>
        </template>

            <template #cell(parts)="{ item }">
                <div class="flex flex-wrap gap-1">
                    <span v-for="part in item.parts" :key="part"
                        class="text-xs px-1.5 py-0.5 rounded bg-gray-100 text-gray-600">{{ part }}</span>
                </div>
            </template>

        <template #cell(weight)="{ item }">
            <span class="tabular-nums">{{ item.weight }} kg</span>
        </template>

        <template #cell(amount)="{ item }">
            <span class="tabular-nums">{{ locale.currencyFormat(currencyCode, item.amount) }}</span>
        </template>
    </Table>
</template>
