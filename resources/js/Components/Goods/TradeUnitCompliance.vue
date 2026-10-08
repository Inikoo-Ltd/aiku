<!--
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed } from "vue"
import { ctrans } from "@/Composables/useTrans"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faShieldCheck, faTree, faBoxOpen, faFlask, faWeightHanging } from "@fal"

library.add(faShieldCheck, faTree, faBoxOpen, faFlask, faWeightHanging)

type Field = { label: string, value: string | null }

type PackagingComponent = {
    id: number
    level: string
    name: string | null
    material: string | null
    material_id_code: string | null
    material_category: string
    weight_g: number | null
    quantity: number
    quantity_per_unit: number
    weight_per_unit_g: number | null
    recycled_content_pct: number | null
    recyclability: string | null
    separable: string | null
    marks: string | null
    national_marks: string | null
}

const props = defineProps<{
    data: {
        gpsr: Field[]
        regulatory: Field[]
        material_composition: { material: string, percentage: number }[]
        material_composition_text: string | null
        eudr: Field[]
        packaging: {
            code: string
            status: string
            shared_with: number
            weight_per_unit_g: number
            components: PackagingComponent[]
        } | null
    }
    tab: string
}>()

const filled = (fields: Field[]) => fields.filter((field) => field.value).length

const eudrStatus = computed(() => props.data.eudr[0]?.value ?? null)
const gpsrFields = computed(() => props.data.gpsr.slice(0, 5))

const sections = computed(() => [
    { key: "gpsr", title: ctrans("GPSR"), icon: "fal fa-shield-check", fields: props.data.gpsr },
    { key: "regulatory", title: ctrans("Regulatory"), icon: "fal fa-flask", fields: props.data.regulatory },
    { key: "eudr", title: ctrans("EUDR raw materials"), icon: "fal fa-tree", fields: props.data.eudr },
])

const grams = (value: number | null) => value === null ? "—" : `${Number(value.toFixed(3))} g`
</script>

<template>
    <div class="p-4 space-y-4 text-sm text-gray-700">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 max-w-5xl">
            <div class="rounded-lg border border-gray-200 bg-white p-3 flex items-center gap-3">
                <FontAwesomeIcon icon="fal fa-shield-check" class="text-[--app-accent]" fixed-width />
                <div>
                    <div class="text-xs text-gray-500">{{ ctrans("GPSR answers") }}</div>
                    <div class="font-medium tabular-nums">{{ filled(gpsrFields) }} / {{ gpsrFields.length }}</div>
                </div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-3 flex items-center gap-3">
                <FontAwesomeIcon icon="fal fa-tree" class="text-[--app-accent]" fixed-width />
                <div class="min-w-0">
                    <div class="text-xs text-gray-500">{{ ctrans("EUDR") }}</div>
                    <div class="font-medium truncate" :title="eudrStatus ?? ''">{{ eudrStatus ?? ctrans("Not declared") }}</div>
                </div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-3 flex items-center gap-3">
                <FontAwesomeIcon icon="fal fa-weight-hanging" class="text-[--app-accent]" fixed-width />
                <div>
                    <div class="text-xs text-gray-500">{{ ctrans("Packaging per unit") }}</div>
                    <div class="font-medium tabular-nums">
                        {{ data.packaging ? grams(data.packaging.weight_per_unit_g) : ctrans("No packaging declared") }}
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 max-w-5xl">
            <section v-for="section in sections" :key="section.key" class="rounded-lg border border-gray-200 bg-white">
                <h3 class="px-3 py-2 border-b border-gray-100 font-semibold flex items-center gap-2">
                    <FontAwesomeIcon :icon="section.icon" class="text-gray-400" fixed-width />
                    {{ section.title }}
                </h3>
                <dl class="divide-y divide-gray-100">
                    <div v-for="field in section.fields" :key="field.label" class="px-3 py-1.5 grid grid-cols-[minmax(7rem,40%)_minmax(0,1fr)] gap-2">
                        <dt class="text-gray-500">{{ field.label }}</dt>
                        <dd :class="field.value ? 'font-medium whitespace-pre-line break-words' : 'text-gray-400'">{{ field.value || "—" }}</dd>
                    </div>
                </dl>
                <div v-if="section.key === 'regulatory' && (data.material_composition.length || data.material_composition_text)" class="px-3 py-2 border-t border-gray-100">
                    <div class="text-gray-500 mb-1">{{ ctrans("Material composition") }}</div>
                    <div v-if="data.material_composition.length" class="flex flex-wrap gap-1">
                        <span v-for="material in data.material_composition" :key="material.material" class="rounded bg-gray-100 px-2 py-0.5 text-xs">
                            {{ material.material }} <span class="tabular-nums text-gray-500">{{ material.percentage }}%</span>
                        </span>
                    </div>
                    <div v-else class="font-medium">{{ data.material_composition_text }}</div>
                </div>
            </section>
        </div>

        <section class="rounded-lg border border-gray-200 bg-white max-w-5xl">
            <h3 class="px-3 py-2 border-b border-gray-100 font-semibold flex items-center gap-2">
                <FontAwesomeIcon icon="fal fa-box-open" class="text-gray-400" fixed-width />
                {{ ctrans("Packaging (PPWR)") }}
                <span v-if="data.packaging" class="font-normal text-gray-500">
                    {{ data.packaging.code }}
                    <template v-if="data.packaging.shared_with">· {{ ctrans("shared with :count other trade units", { count: data.packaging.shared_with }) }}</template>
                </span>
            </h3>
            <div v-if="!data.packaging" class="px-3 py-4 text-gray-500">
                {{ ctrans("No packaging components yet. They come from the Packaging components tab of the supplier product upload.") }}
            </div>
            <div v-else class="overflow-x-auto">
                <table class="min-w-full text-xs">
                    <thead class="text-gray-600 text-left">
                        <tr class="border-b border-gray-100">
                            <th class="px-3 py-1.5 font-medium">{{ ctrans("Level") }}</th>
                            <th class="px-3 py-1.5 font-medium">{{ ctrans("Component") }}</th>
                            <th class="px-3 py-1.5 font-medium">{{ ctrans("Material") }}</th>
                            <th class="px-3 py-1.5 font-medium text-right">{{ ctrans("Weight") }}</th>
                            <th class="px-3 py-1.5 font-medium text-right">{{ ctrans("Qty") }}</th>
                            <th class="px-3 py-1.5 font-medium text-right">{{ ctrans("Per unit") }}</th>
                            <th class="px-3 py-1.5 font-medium text-right">{{ ctrans("Recycled") }}</th>
                            <th class="px-3 py-1.5 font-medium">{{ ctrans("Recyclability") }}</th>
                            <th class="px-3 py-1.5 font-medium">{{ ctrans("Marks") }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="component in data.packaging.components" :key="component.id">
                            <td class="px-3 py-1.5 text-gray-500 whitespace-nowrap">{{ component.level }}</td>
                            <td class="px-3 py-1.5 font-medium">{{ component.name || "—" }}</td>
                            <td class="px-3 py-1.5">
                                {{ component.material || component.material_category }}
                                <span v-if="component.material_id_code" class="text-gray-500">· {{ component.material_id_code }}</span>
                            </td>
                            <td class="px-3 py-1.5 text-right tabular-nums">{{ grams(component.weight_g) }}</td>
                            <td class="px-3 py-1.5 text-right tabular-nums">{{ component.quantity }}</td>
                            <td class="px-3 py-1.5 text-right tabular-nums">{{ grams(component.weight_per_unit_g) }}</td>
                            <td class="px-3 py-1.5 text-right tabular-nums">{{ component.recycled_content_pct === null ? "—" : `${component.recycled_content_pct}%` }}</td>
                            <td class="px-3 py-1.5">{{ component.recyclability || "—" }}</td>
                            <td class="px-3 py-1.5">{{ [component.marks, component.national_marks].filter(Boolean).join(" · ") || "—" }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
