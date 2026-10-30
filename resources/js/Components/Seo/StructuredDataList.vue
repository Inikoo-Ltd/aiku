<script setup lang="ts">
import { computed, ref } from "vue"
import { router } from "@inertiajs/vue3"
import { Textarea } from "primevue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faTimes, faCode, faPencil, faPlus, faSave, faTrashAlt, faExclamationTriangle } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { buildWebpageStructuredData, type WebpageStructuredDataSource } from "@/Iris/Composables/useWebpageStructuredData"
import { routeType } from "@/types/route"

const props = defineProps<{
    source: WebpageStructuredDataSource | null
    customJson?: string | null
    updateRoute?: routeType
    readonly?: boolean
    editable?: boolean
}>()

const emit = defineEmits<{
    "update:customJson": [value: string]
}>()

const SCHEMA_CONTEXT = "https://schema.org"
const NEW_ITEM = -1

const isNode = (value: unknown): value is Record<string, any> => !!value && typeof value === "object" && !Array.isArray(value) && Object.keys(value).length > 0

const toNodes = (value: unknown): Record<string, any>[] => {
    if (Array.isArray(value)) {
        return value.flatMap(toNodes)
    }

    if (!isNode(value)) {
        return []
    }

    if (Array.isArray(value["@graph"])) {
        return value["@graph"].filter(isNode).map((node) => ({ "@context": value["@context"] ?? SCHEMA_CONTEXT, ...node }))
    }

    return [value]
}

const toRow = (node: any, customIndex: number | null) => ({
    type: [node?.["@type"]].flat().filter(Boolean).join(", ") || ctrans("No @type"),
    customIndex,
    isValid: !!node?.["@type"],
    code: JSON.stringify(node, null, 2),
})

const systemStructuredData = computed(() => {
    if (!props.source) {
        return []
    }

    try {
        return buildWebpageStructuredData({
            ...props.source,
            webpage_data: { ...props.source.webpage_data, seo_data: { ...props.source.webpage_data?.seo_data, structured_data: null } },
        })
    } catch {
        return []
    }
})

const customNodes = computed<Record<string, any>[] | null>(() => {
    if (!props.customJson?.trim()) {
        return []
    }

    try {
        return toNodes(JSON.parse(props.customJson))
    } catch {
        return null
    }
})

const rows = computed(() => {
    const systemRows = toNodes(systemStructuredData.value).map((node) => toRow(node, null))

    if (customNodes.value === null) {
        return [...systemRows, { type: ctrans("Custom JSON-LD"), customIndex: null, isCustom: true, isValid: false, code: props.customJson ?? "" }]
    }

    return [
        ...systemRows.map((row) => ({ ...row, isCustom: false })),
        ...customNodes.value.map((node, index) => ({ ...toRow(node, index), isCustom: true })),
    ]
})

const isEditable = computed(() => (!!props.updateRoute || props.editable) && !props.readonly && customNodes.value !== null)

const editingIndex = ref<number | null>(null)
const deletingIndex = ref<number | null>(null)
const draft = ref("")
const saveError = ref<string | null>(null)
const isSaving = ref(false)

const draftCheck = computed(() => {
    if (!draft.value.trim()) {
        return null
    }

    try {
        const nodes = toNodes(JSON.parse(draft.value))
        if (!nodes.length) {
            return { isValid: false, message: ctrans("Write a JSON object, or a list of objects") }
        }

        return {
            isValid: true,
            nodes,
            isOverwritten: nodes.some((node) => [node["@type"]].flat().some((type) => type === "Product" || type === "ProductGroup")),
        }
    } catch (error: any) {
        return { isValid: false, message: ctrans("Invalid JSON: :error", { error: error.message }) }
    }
})

const startEditing = (index: number) => {
    editingIndex.value = index
    deletingIndex.value = null
    saveError.value = null
    draft.value = index === NEW_ITEM ? "" : JSON.stringify(customNodes.value?.[index] ?? {}, null, 2)
}

const save = (nodes: Record<string, any>[]) => {
    if (!props.updateRoute) {
        emit("update:customJson", nodes.length ? JSON.stringify(nodes, null, 2) : "")
        editingIndex.value = null
        deletingIndex.value = null

        return
    }

    isSaving.value = true
    saveError.value = null
    router.patch(route(props.updateRoute.name, props.updateRoute.parameters), {
        structured_data: nodes.length ? JSON.stringify(nodes, null, 2) : null,
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            editingIndex.value = null
            deletingIndex.value = null
        },
        onError: (errors) => {
            saveError.value = Object.values(errors).join(" ")
        },
        onFinish: () => {
            isSaving.value = false
        },
    })
}

const saveDraft = () => {
    if (!draftCheck.value?.isValid || !draftCheck.value.nodes) {
        return
    }

    const nodes = [...(customNodes.value ?? [])]
    if (editingIndex.value === NEW_ITEM) {
        nodes.push(...draftCheck.value.nodes)
    } else if (editingIndex.value !== null) {
        nodes.splice(editingIndex.value, 1, ...draftCheck.value.nodes)
    }

    save(nodes)
}

const deleteNode = (index: number) => {
    save((customNodes.value ?? []).filter((_, nodeIndex) => nodeIndex !== index))
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <div class="flex items-center justify-between gap-2 text-xs">
            <span class="font-medium text-gray-600">{{ ctrans("Structured data on this page") }}</span>
            <span class="text-gray-500">{{ ctrans(":count implemented", { count: rows.length }) }}</span>
        </div>

        <details v-for="(row, index) in rows" :key="index" class="group rounded-md border border-gray-200">
            <summary class="flex cursor-pointer list-none flex-wrap items-center gap-2 px-3 py-2 [&::-webkit-details-marker]:hidden">
                <span class="font-mono text-sm font-semibold text-gray-800">{{ row.type }}</span>
                <span class="whitespace-nowrap rounded-full px-2 py-0.5 text-[11px] font-medium"
                    :class="row.isCustom ? 'bg-[--app-accent-soft] text-[--app-accent-strong]' : 'bg-gray-100 text-gray-600'">
                    {{ row.isCustom ? ctrans("Custom JSON-LD") : ctrans("Generated by system") }}
                </span>
                <span class="inline-flex items-center gap-1 text-xs font-medium" :class="row.isValid ? 'text-green-700' : 'text-red-700'">
                    <FontAwesomeIcon :icon="row.isValid ? faCheck : faTimes" fixed-width aria-hidden="true" />
                    {{ row.isValid ? ctrans("Valid") : ctrans("Invalid") }}
                </span>
                <span class="ml-auto flex flex-wrap items-center gap-2">
                    <span v-if="isEditable && row.customIndex !== null" class="flex flex-wrap gap-2" @click.prevent>
                        <template v-if="deletingIndex === row.customIndex">
                            <Button type="tertiary" size="xs" :label="ctrans('Confirm delete')" :loading="isSaving" @click="deleteNode(row.customIndex)">
                                <template #icon>
                                    <FontAwesomeIcon :icon="faExclamationTriangle" class="text-amber-500" fixed-width aria-hidden="true" />
                                </template>
                            </Button>
                            <Button type="tertiary" size="xs" :label="ctrans('Cancel')" @click="deletingIndex = null">
                                <template #icon>
                                    <FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
                                </template>
                            </Button>
                        </template>
                        <template v-else>
                            <Button type="tertiary" size="xs" :label="ctrans('Edit')" @click="startEditing(row.customIndex)">
                                <template #icon>
                                    <FontAwesomeIcon :icon="faPencil" class="text-[--app-accent]" fixed-width aria-hidden="true" />
                                </template>
                            </Button>
                            <Button type="tertiary" size="xs" :label="ctrans('Delete')" @click="deletingIndex = row.customIndex; editingIndex = null">
                                <template #icon>
                                    <FontAwesomeIcon :icon="faTrashAlt" class="text-red-500" fixed-width aria-hidden="true" />
                                </template>
                            </Button>
                        </template>
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded border border-gray-300 bg-white px-2.5 py-1 text-xs font-medium text-gray-700 group-hover:bg-gray-50">
                        <FontAwesomeIcon :icon="faCode" class="text-[--app-accent]" fixed-width aria-hidden="true" />
                        <span class="group-open:hidden">{{ ctrans("View code") }}</span>
                        <span class="hidden group-open:inline">{{ ctrans("Hide code") }}</span>
                    </span>
                </span>
            </summary>
            <pre class="m-0 mx-3 mb-3 max-h-64 overflow-auto rounded bg-gray-50 px-3 py-2 font-mono text-xs leading-5 text-gray-700">{{ row.code }}</pre>
        </details>
        <div v-if="!rows.length" class="rounded bg-gray-50 px-3 py-2 text-sm italic text-gray-400">{{ ctrans("No structured data on this page") }}</div>

        <template v-if="isEditable">
            <div v-if="editingIndex === null">
                <Button type="tertiary" :label="ctrans('Add new structured data')" @click="startEditing(NEW_ITEM)">
                    <template #icon>
                        <FontAwesomeIcon :icon="faPlus" class="text-green-600" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </div>

            <div v-else class="flex flex-col gap-2 rounded-md border border-[--app-accent-muted] bg-[--app-accent-soft] p-3">
                <label for="structured-data-draft" class="text-sm font-semibold text-gray-800">
                    {{ editingIndex === NEW_ITEM ? ctrans("New structured data") : ctrans("Edit structured data") }}
                </label>
                <Textarea id="structured-data-draft" v-model="draft" rows="10" spellcheck="false" wrap="off"
                    :placeholder="ctrans('Paste or write JSON-LD here')" class="w-full font-mono text-xs leading-5" />
                <span v-if="draftCheck?.isValid" class="text-xs font-medium text-green-700">{{ ctrans("Valid JSON") }}</span>
                <span v-else-if="draftCheck" class="text-xs font-medium text-red-700">{{ draftCheck.message }}</span>
                <div v-if="draftCheck?.isOverwritten" class="flex gap-2 rounded border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                    <FontAwesomeIcon :icon="faExclamationTriangle" class="mt-0.5" fixed-width aria-hidden="true" />
                    <span>{{ ctrans("@type 'ProductGroup' or 'Product' will have no effect, as it will be overwritten by the system.") }}</span>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button type="tertiary" :label="updateRoute ? ctrans('Save') : ctrans('Apply')" :loading="isSaving" :disabled="!draftCheck?.isValid" @click="saveDraft">
                        <template #icon>
                            <FontAwesomeIcon :icon="faSave" class="text-green-600" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                    <Button type="tertiary" :label="ctrans('Cancel')" @click="editingIndex = null">
                        <template #icon>
                            <FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                </div>
            </div>
        </template>
        <p v-if="saveError" class="text-xs text-red-600">{{ saveError }}</p>
    </div>
</template>
