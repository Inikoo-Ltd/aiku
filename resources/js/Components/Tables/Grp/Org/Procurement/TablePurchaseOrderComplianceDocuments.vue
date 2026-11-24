<script setup lang="ts">
import { ref } from "vue"
import { Link, router } from "@inertiajs/vue3"
import axios from "axios"
import Popover from "primevue/popover"
import Table from "@/Components/Table/Table.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { routeType } from "@/types/route"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheck, faUpload, faExclamationTriangle, faDownload } from "@fal"
library.add(faCheck, faUpload, faExclamationTriangle, faDownload)

interface DocumentFile {
    name: string
    purchase_order_reference: string | null
    download_route: routeType
}

interface Document {
    scope: string
    label: string
    count: number
    files: DocumentFile[]
    required: boolean
    supplier_only: boolean
}

const props = defineProps<{
    data: object
    tab?: string
    compliance?: {
        eta: string | null
        deadline: string | null
        buffer_days: number
        missing: number
        overdue: boolean
        upload_route: routeType | null
    } | null
}>()

const documentScopes = ["sds", "ifra", "clp", "allergen_declarations", "doc", "cpsr", "test_reports"]

const uploading = ref<string | null>(null)
const uploadError = ref<{ key: string, message: string } | null>(null)

const filesPopover = ref()
const openedDocument = ref<Document | null>(null)

function showFiles(document: Document, event: Event) {
    openedDocument.value = document
    filesPopover.value?.toggle(event)
}

async function upload(tradeUnitId: number, document: Document, event: Event) {
    const input = event.target as HTMLInputElement
    const files = Array.from(input.files ?? [])
    input.value = ""
    if (!files.length || !props.compliance?.upload_route) {
        return
    }
    const key = `${tradeUnitId}-${document.scope}`
    uploading.value = key
    uploadError.value = null
    try {
        await axios.post(
            route(props.compliance.upload_route.name, { ...props.compliance.upload_route.parameters, tradeUnit: tradeUnitId }),
            { attachments: files, scope: document.scope },
            { headers: { "Content-Type": "multipart/form-data" } }
        )
        router.reload({ only: [props.tab ?? "compliance", "compliance_summary"] })
    } catch (error: any) {
        uploadError.value = { key, message: error?.response?.data?.message ?? ctrans("Something went wrong") }
    } finally {
        uploading.value = null
    }
}
</script>

<template>
    <div>
        <p v-if="compliance" class="px-4 pt-3 text-sm" :class="compliance.overdue ? 'text-red-600' : 'text-gray-600'">
            <FontAwesomeIcon v-if="compliance.overdue" icon="fal fa-exclamation-triangle" fixed-width aria-hidden="true" />
            <template v-if="compliance.deadline">
                {{ ctrans("Documents due by :date, the arrival estimate (:eta) less :days days.", { date: useFormatTime(compliance.deadline, { formatTime: "d MMM yyyy" }), eta: useFormatTime(compliance.eta as string, { formatTime: "d MMM yyyy" }), days: compliance.buffer_days }) }}
            </template>
            <template v-else>{{ ctrans("No arrival estimate yet, so no due date for the documents.") }}</template>
            <template v-if="compliance.missing"> {{ ctrans(":count required documents missing.", { count: compliance.missing }) }}</template>
        </p>

        <Table :resource="data" :name="tab">
            <template #cell(code)="{ item }">
                <Link :href="route(item.route.name, item.route.parameters)" class="primaryLink whitespace-nowrap">
                    {{ item.code }}
                </Link>
            </template>

            <template v-for="scope in documentScopes" :key="scope" #[`cell(${scope})`]="{ item }">
                <div class="flex flex-col items-center">
                    <div class="flex items-center justify-center gap-2">
                        <button
                            v-if="item[scope]?.count"
                            type="button"
                            class="text-green-600 tabular-nums hover:text-green-700"
                            v-tooltip="ctrans(':count uploaded', { count: item[scope].count })"
                            @click="(event) => showFiles(item[scope], event)"
                        >
                            <FontAwesomeIcon icon="fal fa-check" fixed-width aria-hidden="true" />
                            <template v-if="item[scope].count > 1">{{ item[scope].count }}</template>
                        </button>
                        <span v-else :class="item[scope]?.required ? 'text-red-600' : 'text-gray-300'" v-tooltip="item[scope]?.required ? ctrans('Required') : ctrans('Optional')">-</span>
                        <label v-if="compliance?.upload_route" class="cursor-pointer text-gray-400 hover:text-gray-700" v-tooltip="ctrans('Upload :document', { document: item[scope]?.label })">
                            <LoadingIcon v-if="uploading === `${item.id}-${scope}`" />
                            <FontAwesomeIcon v-else icon="fal fa-upload" fixed-width aria-hidden="true" />
                            <input type="file" multiple accept="image/*,.pdf" class="hidden" :disabled="!!uploading" @change="(event) => upload(item.id, item[scope], event)" />
                        </label>
                    </div>
                    <p v-if="uploadError?.key === `${item.id}-${scope}`" class="mt-1 text-xs text-red-600">{{ uploadError.message }}</p>
                </div>
            </template>
        </Table>

        <Popover ref="filesPopover">
            <div v-if="openedDocument" class="w-72">
                <p class="mb-2 text-sm font-semibold text-gray-700">{{ openedDocument.label }}</p>
                <ul class="divide-y divide-gray-100">
                    <li v-for="(file, index) in openedDocument.files" :key="index">
                        <a
                            :href="route(file.download_route.name, file.download_route.parameters)"
                            class="flex items-center gap-2 py-1.5 text-sm text-gray-700 hover:text-[var(--app-accent)]"
                        >
                            <FontAwesomeIcon icon="fal fa-download" fixed-width aria-hidden="true" />
                            <span class="truncate">{{ file.name }}</span>
                        </a>
                        <p v-if="file.purchase_order_reference" class="-mt-1 pb-1.5 pl-6 text-xs text-gray-400">{{ ctrans("from PO :reference", { reference: file.purchase_order_reference }) }}</p>
                    </li>
                </ul>
            </div>
        </Popover>
    </div>
</template>
