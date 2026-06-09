<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { ref } from 'vue'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import TableChatConversations from '@/Components/Tables/Grp/Chat/TableChatConversations.vue'
import { capitalize } from '@/Composables/capitalize'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faComments, faFileDownload } from '@fal'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { PageHeadingTypes } from '@/types/PageHeading'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'
import InputNumber from 'primevue/inputnumber'
import DatePicker from 'primevue/datepicker'
import Button from 'primevue/button'

library.add(faComments, faFileDownload)

interface ShopOption {
    id: number
    name: string
    code: string
}

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    data: object
    exportRoute: { name: string; parameters: Record<string, string> }
    shops: ShopOption[]
}>()

const showExportModal = ref(false)
const exportFormat = ref<'jsonl' | 'csv'>('jsonl')
const exportShopId = ref<number | null>(null)
const exportStatus = ref('closed')
const exportSentiment = ref<string | null>(null)
const exportFrom = ref<Date | null>(null)
const exportTo = ref<Date | null>(null)
const exportMinTurns = ref(2)
const exportSystemPrompt = ref('You are a helpful customer service agent. Be professional, concise, and helpful.')

const shopOptions = [
    { label: 'All shops', value: null },
    ...props.shops.map(s => ({ label: `${s.name} (${s.code})`, value: s.id })),
]

const statusOptions = [
    { label: 'Closed only (recommended)', value: 'closed' },
    { label: 'All statuses', value: '' },
]

const sentimentOptions = [
    { label: 'All sentiments', value: null },
    { label: 'Positive only', value: 'positive' },
    { label: 'Neutral only', value: 'neutral' },
    { label: 'Negative only', value: 'negative' },
]

const formats: { value: 'jsonl' | 'csv'; label: string; desc: string }[] = [
    { value: 'jsonl', label: 'JSONL', desc: 'OpenAI / Fine-tuning' },
    { value: 'csv', label: 'CSV', desc: 'Spreadsheet' },
]

function formatDate(date: Date | null): string {
    if (!date) return ''
    return date.toISOString().split('T')[0]
}

function buildExportUrl(): string {
    const base = route(props.exportRoute.name, props.exportRoute.parameters)
    const params = new URLSearchParams({ format: exportFormat.value })
    if (exportShopId.value) params.set('shop_id', String(exportShopId.value))
    if (exportStatus.value) params.set('status', exportStatus.value)
    if (exportSentiment.value) params.set('sentiment', exportSentiment.value)
    if (exportFrom.value) params.set('from', formatDate(exportFrom.value))
    if (exportTo.value) params.set('to', formatDate(exportTo.value))
    if (exportMinTurns.value > 1) params.set('min_turns', String(exportMinTurns.value))
    if (exportSystemPrompt.value && exportFormat.value === 'jsonl') {
        params.set('system_prompt', exportSystemPrompt.value)
    }
    return `${base}?${params.toString()}`
}

function triggerExport(): void {
    window.location.href = buildExportUrl()
    showExportModal.value = false
}
</script>

<template>
    <Head :title="capitalize(title)" />

    <PageHeading :data="pageHead">
        <!-- Override the header action button to open modal instead of navigating -->
        <template #button-export-data>
            <button
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-md border border-gray-300 text-xs font-medium text-gray-600 hover:bg-gray-50 transition-colors"
                @click="showExportModal = true"
            >
                <FontAwesomeIcon :icon="['fal', 'fa-file-download']" class="text-sm" />
                Export Data
            </button>
        </template>
    </PageHeading>

    <TableChatConversations :data="data" />

    <!-- Export Dialog -->
    <Dialog
        v-model:visible="showExportModal"
        modal
        header="Export Data"
        :style="{ width: '32rem' }"
        :draggable="false"
    >
        <div class="space-y-4 pt-1">

            <!-- Format: card select -->
            <div class="space-y-1">
                <label class="text-xs font-medium text-gray-700">Format</label>
                <div class="flex gap-2">
                    <button
                        v-for="fmt in formats"
                        :key="fmt.value"
                        class="flex-1 rounded-lg border px-3 py-2.5 text-left transition-colors"
                        :class="exportFormat === fmt.value
                            ? 'border-indigo-400 bg-indigo-50 ring-1 ring-indigo-300'
                            : 'border-gray-200 hover:bg-gray-50'"
                        @click="exportFormat = fmt.value"
                    >
                        <p class="text-xs font-semibold text-gray-800">{{ fmt.label }}</p>
                        <p class="text-[11px] text-gray-500">{{ fmt.desc }}</p>
                    </button>
                </div>
            </div>

            <!-- Shop -->
            <div class="space-y-1">
                <label class="text-xs font-medium text-gray-700">Shop</label>
                <Select
                    v-model="exportShopId"
                    :options="shopOptions"
                    option-label="label"
                    option-value="value"
                    placeholder="All shops"
                    filter
                    class="w-full"
                />
                <p class="text-[11px] text-gray-400">Export from a specific shop, or all shops in this organisation</p>
            </div>

            <!-- Status + Sentiment -->
            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1">
                    <label class="text-xs font-medium text-gray-700">Status</label>
                    <Select
                        v-model="exportStatus"
                        :options="statusOptions"
                        option-label="label"
                        option-value="value"
                        class="w-full"
                    />
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-medium text-gray-700">Sentiment</label>
                    <Select
                        v-model="exportSentiment"
                        :options="sentimentOptions"
                        option-label="label"
                        option-value="value"
                        class="w-full"
                    />
                </div>
            </div>

            <!-- Date range -->
            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1">
                    <label class="text-xs font-medium text-gray-700">From date</label>
                    <DatePicker v-model="exportFrom" date-format="yy-mm-dd" class="w-full" show-button-bar />
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-medium text-gray-700">To date</label>
                    <DatePicker v-model="exportTo" date-format="yy-mm-dd" class="w-full" show-button-bar />
                </div>
            </div>

            <!-- Min turns -->
            <div class="space-y-1">
                <label class="text-xs font-medium text-gray-700">Minimum turns</label>
                <InputNumber v-model="exportMinTurns" :min="1" :max="50" show-buttons class="w-full" />
                <p class="text-[11px] text-gray-400">Skip conversations with fewer than this many messages</p>
            </div>

            <!-- Info -->
            <div class="rounded-lg bg-blue-50 border border-blue-100 px-3 py-2.5 text-[11px] text-blue-700 leading-relaxed">
                Only <strong>closed</strong> conversations with ≥ {{ exportMinTurns }} turns are exported.
                Output is ready for OpenAI fine-tuning, Llama, or Mistral instruction tuning.
            </div>
        </div>

        <template #footer>
            <Button label="Cancel" severity="secondary" text @click="showExportModal = false" />
            <Button @click="triggerExport">
                <FontAwesomeIcon :icon="['fal', 'fa-file-download']" class="mr-1.5" />
                Download {{ exportFormat.toUpperCase() }}
            </Button>
        </template>
    </Dialog>
</template>
