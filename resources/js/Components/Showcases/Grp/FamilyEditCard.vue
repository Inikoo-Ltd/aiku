<script setup lang="ts">
import { computed } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faTimes } from "@fal"
import FieldForm from "@/Components/Forms/FieldForm.vue"
import HelpTip from "@/Components/Utils/HelpTip.vue"
import { seoFieldHelp } from "@/Composables/seoFieldHelp"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"

export interface FamilyEditCardData {
    key: string | null
    label: string | null
    owner: string | null
    check: { ok: boolean, note: string } | null
    keywords?: string[]
    update_route?: routeType
    fields: Record<string, any>
}

const props = defineProps<{
    card: FamilyEditCardData
    fieldKey: string
}>()

const emit = defineEmits<{
    fieldChange: [field: string, value: string | boolean]
}>()

const onTyped = (field: string, event: Event) => {
    const target = event.target

    if (target instanceof HTMLInputElement && target.type === "checkbox") {
        emit("fieldChange", field, target.checked)
    } else if (target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement) {
        emit("fieldChange", field, target.value)
    }
}

const helpText = computed(() => seoFieldHelp(props.card.key ?? (props.card.keywords ? "keywords" : null)))
</script>

<template>
    <article class="flex scroll-mt-4 flex-col gap-2" :class="card.label ? 'rounded-md border border-gray-200 p-3' : ''">
        <div v-if="card.label" class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex flex-col gap-0.5">
                <h3 class="text-sm font-semibold text-gray-800">
                    {{ card.label }}
                    <HelpTip v-if="helpText" :text="helpText" />
                </h3>
                <span v-if="card.owner" class="text-xs text-gray-500">{{ card.owner }}</span>
            </div>
            <span v-if="card.check" class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium"
                :class="card.check.ok ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
                <FontAwesomeIcon :icon="card.check.ok ? faCheck : faTimes" fixed-width aria-hidden="true" />
                {{ card.check.note }}
            </span>
        </div>

        <div v-if="card.keywords" class="flex flex-col gap-1.5">
            <span class="text-xs font-medium text-gray-600">{{ ctrans("Current") }}</span>
            <div v-if="card.keywords.length" class="flex flex-wrap gap-2 rounded bg-gray-50 px-3 py-2">
                <span v-for="keyword in card.keywords" :key="keyword" class="px-2 py-0.5 rounded border border-gray-200 bg-white text-sm">
                    {{ keyword }}
                </span>
            </div>
            <div v-else class="rounded bg-gray-50 px-3 py-2 text-sm italic text-gray-400">{{ ctrans("Empty") }}</div>
        </div>

        <div v-for="(fieldData, fieldName) in card.fields" :key="`${fieldKey}-${fieldName}`"
            @input="onTyped(fieldName as string, $event)" @change="onTyped(fieldName as string, $event)">
            <FieldForm :field="(fieldName as string)" :fieldData="fieldData" :args="{ updateRoute: card.update_route }" :refForms="undefined" />
        </div>
    </article>
</template>
