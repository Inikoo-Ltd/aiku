<!--
  - Author Louis Perez
  - Created on 17-10-2026-21h-30m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { computed } from "vue"
import MultiSelect from "primevue/multiselect"
import Select from "primevue/select"
import ToggleSwitch from "primevue/toggleswitch"
import IngredientsField from "@/Components/Forms/Fields/IngredientsField.vue"
import ProductDevelopmentMarketSelect from "@/Components/Goods/ProductDevelopment/ProductDevelopmentMarketSelect.vue"
import { ctrans } from "@/Composables/useTrans"
import type { ChecklistGroupOptions, ChecklistInputType } from "@/Components/Goods/ProductDevelopment/productDevelopmentChecklist"

const props = defineProps<{
    type: Extract<ChecklistInputType, "pictograms" | "label_marks" | "ingredients">
    options: ChecklistGroupOptions
    readonly?: boolean
    ingredientOptions?: { slug: string, name: string }[]
}>()

const model = defineModel<Record<string, any>>({ required: true })

const marks = computed(() => props.options.label_marks)
const chosenPictograms = computed(() => (props.options.pictograms ?? []).filter((pictogram) => model.value[pictogram.key]))
const labelFor = (list: { value: string, label: string }[] | undefined, values: string[] | null | undefined) => (values ?? []).map((value) => list?.find((option) => option.value === value)?.label ?? value)
</script>

<template>
    <div class="text-sm">
        <template v-if="type === 'pictograms'">
            <div v-if="readonly" class="flex flex-wrap items-center gap-2">
                <span v-for="pictogram in chosenPictograms" :key="pictogram.key" class="flex items-center gap-1.5 rounded-md border border-gray-200 bg-white px-2 py-1 text-xs text-gray-700">
                    <img :src="pictogram.icon" :alt="pictogram.label" class="h-5 w-5 object-contain" />
                    {{ pictogram.label }}
                </span>
                <span v-if="!chosenPictograms.length" class="text-gray-700">{{ ctrans("No hazard pictograms") }}</span>
            </div>
            <div v-else class="grid gap-x-4 gap-y-2 sm:grid-cols-2">
                <label v-for="pictogram in options.pictograms ?? []" :key="pictogram.key" class="flex items-center gap-2 rounded-md border border-gray-200 bg-white px-2 py-1.5">
                    <img :src="pictogram.icon" :alt="pictogram.label" class="h-7 w-7 shrink-0 object-contain" />
                    <span class="min-w-0 flex-1 text-gray-700">{{ pictogram.label }}</span>
                    <ToggleSwitch v-model="model[pictogram.key]" class="shrink-0" />
                </label>
            </div>
        </template>

        <template v-else-if="type === 'label_marks' && marks">
            <dl v-if="readonly" class="grid gap-x-6 gap-y-1.5 sm:grid-cols-2">
                <div><dt class="inline text-gray-500">{{ ctrans("Markets") }}:</dt> <dd class="inline text-gray-900">{{ labelFor(marks.markets, model.markets).join(", ") || "—" }}</dd></div>
                <div><dt class="inline text-gray-500">{{ ctrans("Languages") }}:</dt> <dd class="inline text-gray-900">{{ (model.languages ?? []).map((code: string) => code.toUpperCase()).join(", ") || "—" }}</dd></div>
                <div><dt class="inline text-gray-500">{{ ctrans("PAO / Expiry / Best before") }}:</dt> <dd class="inline text-gray-900">{{ labelFor(marks.best_before, model.best_before ? [model.best_before] : [])[0] ?? "—" }}</dd></div>
                <div><dt class="inline text-gray-500">{{ ctrans("Packaging material codes") }}:</dt> <dd class="inline text-gray-900">{{ (model.packaging_material_codes ?? []).map((value: string) => marks!.packaging.find((option) => option.value === value)?.code ?? value).join(", ") || "—" }}</dd></div>
                <div class="sm:col-span-2"><dt class="inline text-gray-500">{{ ctrans("On the label") }}:</dt> <dd class="inline text-gray-900">{{ [...marks.presence.filter((mark) => model[mark.key]).map((mark) => mark.label), ...(model.show_net_quantity ? [ctrans('Net quantity')] : [])].join(", ") || ctrans("None") }}</dd></div>
            </dl>
            <div v-else class="space-y-3">
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="space-y-1">
                        <span class="text-xs text-gray-500">{{ ctrans("Markets") }}</span>
                        <ProductDevelopmentMarketSelect v-model="model.markets" :options="marks.markets" :placeholder="ctrans('Select markets')" />
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs text-gray-500">{{ ctrans("Languages") }}</span>
                        <MultiSelect v-model="model.languages" :options="marks.languages" optionLabel="label" optionValue="value" filter display="chip" :placeholder="ctrans('Select languages')" class="w-full" overlayClass="text-sm" />
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs text-gray-500">{{ ctrans("PAO / Expiry / Best before") }}</span>
                        <Select v-model="model.best_before" :options="marks.best_before" optionLabel="label" optionValue="value" showClear :placeholder="ctrans('Select an option')" class="w-full" overlayClass="text-sm" />
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs text-gray-500">{{ ctrans("Packaging material codes") }}</span>
                        <MultiSelect v-model="model.packaging_material_codes" :options="marks.packaging" optionLabel="label" optionValue="value" filter display="comma" :placeholder="ctrans('Select packaging materials')" class="w-full" overlayClass="text-sm" />
                    </label>
                </div>
                <div class="grid gap-x-4 gap-y-2 sm:grid-cols-2">
                    <label v-for="mark in [...marks.presence, { key: 'show_net_quantity', label: ctrans('Net quantity') }, { key: 'packaging_material_codes_show', label: ctrans('Show packaging codes') }]" :key="mark.key" class="flex items-center justify-between gap-2 rounded-md border border-gray-200 bg-white px-3 py-2">
                        <span class="text-gray-700">{{ mark.label }}</span>
                        <ToggleSwitch v-model="model[mark.key]" class="shrink-0" />
                    </label>
                </div>
            </div>
        </template>

        <template v-else-if="type === 'ingredients' && options.ingredients">
            <div v-if="readonly" class="flex flex-wrap gap-1.5">
                <span v-for="ingredient in ingredientOptions ?? []" :key="ingredient.slug" class="rounded border border-gray-200 bg-white px-2 py-0.5 text-xs text-gray-800">{{ ingredient.name }}</span>
                <span v-if="!(ingredientOptions ?? []).length" class="text-gray-500">—</span>
            </div>
            <IngredientsField v-else :form="model" fieldName="ingredients" :options="ingredientOptions ?? []" :fieldData="options.ingredients" />
        </template>
    </div>
</template>
