<script setup lang="ts">
import { computed, inject, ref } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faInfoCircle, faTimes, faEye, faCog } from "@fal"
import { faCheck } from "@far"
import { library } from "@fortawesome/fontawesome-svg-core"
import { ctrans } from "@/Composables/useTrans"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { useFormatTime } from "@/Composables/useFormatTime"
import InformationIcon from "@/Components/Utils/InformationIcon.vue"
import { ToggleSwitch } from "primevue"

library.add(faInfoCircle, faTimes, faEye, faCog, faCheck)

interface ChargeData {
    id: number
    slug: string
    code: string
    name: string
    label?: string
    description?: string
    state: string
    created_at: string
    updated_at: string
    amount: string
    min_order?: number
    currency_code: string
}

const props = defineProps<{
    data: {
        charge: ChargeData
    }
}>()

const locale = inject("locale", aikuLocaleStructure)

const previewToggleValue = ref(false)

const charge = computed(() => props.data?.charge)
const isActive = computed(() => charge.value?.state === "active")
const money = (value: number | string | undefined) => locale.currencyFormat(charge.value.currency_code, +(value ?? 0))

const rows = computed(() => [
    { label: ctrans("Code"), value: charge.value.code, mono: true },
    { label: ctrans("Slug"), value: charge.value.slug, mono: true },
    { label: ctrans("Label"), value: charge.value.label || "—" },
    { label: ctrans("Created"), value: charge.value.created_at ? useFormatTime(charge.value.created_at) : "—" },
    { label: ctrans("Last updated"), value: charge.value.updated_at ? useFormatTime(charge.value.updated_at) : "—" },
])
</script>

<template>
    <div v-if="charge" class="grid grid-cols-1 gap-4 p-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
        <div class="rounded-md border border-gray-200 bg-white">
            <div class="flex items-start justify-between gap-4 border-b border-gray-100 px-5 py-4">
                <div class="min-w-0">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ ctrans("Amount") }}</div>
                    <div class="mt-1 text-3xl font-semibold tabular-nums text-gray-900">{{ money(charge.amount) }}</div>
                </div>
                <span
                    class="inline-flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-0.5 text-xs font-semibold capitalize"
                    :class="isActive ? 'border-green-300 bg-green-50 text-green-700' : 'border-gray-300 bg-gray-50 text-gray-600'">
                    <span class="h-1.5 w-1.5 rounded-full" :class="isActive ? 'bg-green-500' : 'bg-gray-400'" />
                    {{ charge.state }}
                </span>
            </div>

            <div class="flex items-start gap-3 border-b border-gray-100 px-5 py-3 text-sm">
                <FontAwesomeIcon icon="fal fa-cog" fixed-width class="mt-0.5 shrink-0 text-[--app-accent-strong]" aria-hidden="true" />
                <div>
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ ctrans("When it is applied") }}</div>
                    <div class="mt-0.5 text-gray-800">
                        <template v-if="charge.min_order">{{ ctrans("Automatically, to orders below :amount", { amount: money(charge.min_order) }) }}</template>
                        <template v-else>{{ ctrans("Only when selected on an order") }}</template>
                    </div>
                </div>
            </div>

            <dl class="divide-y divide-gray-100 px-5 text-sm">
                <div v-for="row in rows" :key="row.label" class="flex items-center justify-between gap-4 py-2.5">
                    <dt class="text-gray-500">{{ row.label }}</dt>
                    <dd class="truncate text-right text-gray-900" :class="row.mono && 'font-mono text-xs'">{{ row.value }}</dd>
                </div>
            </dl>
        </div>

        <div class="flex flex-col gap-4">
            <div class="rounded-md border border-gray-200 bg-white">
                <div class="flex items-center gap-2 border-b border-gray-100 px-5 py-3">
                    <FontAwesomeIcon icon="fal fa-eye" fixed-width class="text-[--app-accent-strong]" aria-hidden="true" />
                    <span class="text-sm font-semibold text-gray-800">{{ ctrans("Customer view") }}</span>
                    <span v-tooltip="ctrans('This is what customers see when this charge appears in their basket. The toggle only works here as a preview.')" class="text-gray-400">
                        <FontAwesomeIcon icon="fal fa-info-circle" fixed-width aria-hidden="true" />
                    </span>
                </div>
                <div class="flex items-center justify-center gap-3 bg-gray-50 px-5 py-6 text-sm">
                    <span class="flex items-center gap-1 text-gray-800">
                        <InformationIcon v-if="charge.description" :information="charge.description" />
                        {{ charge.label ?? charge.name }}
                        <span class="text-gray-400">({{ money(charge.amount) }})</span>
                    </span>
                    <ToggleSwitch v-model="previewToggleValue">
                        <template #handle="{ checked }">
                            <FontAwesomeIcon v-if="checked" icon="far fa-check" class="text-sm text-green-500" fixed-width aria-hidden="true" />
                            <FontAwesomeIcon v-else icon="fal fa-times" class="text-sm text-red-500" fixed-width aria-hidden="true" />
                        </template>
                    </ToggleSwitch>
                </div>
            </div>

            <div class="rounded-md border border-gray-200 bg-white px-5 py-4">
                <div class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ ctrans("Description") }}</div>
                <p v-if="charge.description" class="mt-1.5 whitespace-pre-line text-sm leading-relaxed text-gray-700">{{ charge.description }}</p>
                <p v-else class="mt-1.5 text-sm italic text-gray-400">{{ ctrans("No description") }}</p>
            </div>
        </div>
    </div>

    <div v-else class="m-4 rounded-md border border-dashed border-gray-300 bg-gray-50 p-8 text-center text-sm text-gray-500">
        {{ ctrans("No charge information to display") }}
    </div>
</template>
