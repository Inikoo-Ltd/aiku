<script setup lang="ts">
import { ctrans } from "@/Composables/useTrans"
import Checkbox from "primevue/checkbox"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faExclamationTriangle, faQuestionCircle, faLink, faInfoCircle } from "@fal"

library.add(faExclamationTriangle, faQuestionCircle, faLink, faInfoCircle)

export interface SupplierProductFinding {
    level: "error" | "block" | "link" | "warning"
    code: string
    column: string | null
    message: string
    source?: string
}

const props = defineProps<{
    id: string
    label?: string
    required?: boolean
    placeholder?: string | null
    findings: SupplierProductFinding[]
    isAccepted: (finding: SupplierProductFinding) => boolean
}>()

const model = defineModel<string>()

const emits = defineEmits<{
    (e: "accept", finding: SupplierProductFinding, accepted: boolean): void
    (e: "blur"): void
}>()

const levelClass = {
    error: "text-red-700",
    block: "text-orange-700",
    link: "text-sky-700",
    warning: "text-amber-700",
}
const levelIcon = {
    error: "fal fa-exclamation-triangle",
    block: "fal fa-question-circle",
    link: "fal fa-link",
    warning: "fal fa-info-circle",
}

const isOpen = (finding: SupplierProductFinding) => finding.level === "error" || (["block", "link"].includes(finding.level) && !props.isAccepted(finding))
</script>

<template>
    <div>
        <label v-if="label" :for="id" class="block text-xs font-medium text-gray-600">
            {{ label }}<span v-if="required" class="text-red-600"> *</span>
        </label>
        <input
            v-if="model !== undefined"
            :id="id"
            v-model="model"
            type="text"
            class="mt-1 w-full rounded border-gray-300 py-1 text-sm"
            :class="findings.some((finding) => finding.level === 'error') && 'border-red-400'"
            :placeholder="placeholder ?? undefined"
            @blur="emits('blur')"
        />
        <ul v-if="findings.length" class="mt-1 space-y-1 text-xs">
            <li v-for="finding in findings" :key="finding.code" :data-finding-open="isOpen(finding) ? '' : undefined" :class="levelClass[finding.level]">
                <span class="flex items-start gap-1.5">
                    <FontAwesomeIcon :icon="levelIcon[finding.level]" fixed-width class="mt-0.5" />
                    <span>
                        {{ finding.message }}
                        <span v-if="finding.source === 'jev'" class="text-gray-500">({{ ctrans("AI") }})</span>
                    </span>
                </span>
                <label v-if="['block', 'link'].includes(finding.level)" :for="`${id}-${finding.code}`" class="mt-1 ml-5 inline-flex cursor-pointer items-center gap-2 text-gray-600">
                    <Checkbox
                        :inputId="`${id}-${finding.code}`"
                        :modelValue="isAccepted(finding)"
                        binary
                        @update:modelValue="(checked: boolean) => emits('accept', finding, checked)"
                    />
                    {{ finding.level === "link" ? ctrans("Add this supplier to it") : ctrans("This is OK, I accept responsibility") }}
                </label>
            </li>
        </ul>
    </div>
</template>
