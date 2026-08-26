<script setup lang="ts">
import { computed, inject } from "vue"
import { getStyles } from "@/Composables/styles"
import { ctrans } from "@/Composables/useTrans"
import Image from "@/Common/Components/Image.vue"
import LinkIris from "@/Iris/Components/LinkIris.vue"
import {
    COMPARISON_TEMPLATES,
    type ComparisonTemplate,
    type ComparisonTemplateItem,
} from "@/Composables/comparisonTemplates"
import type {
    CategoryComparisonValue,
    ComparisonFamily,
} from "@/Iris/Components/BlocksUtils/CategoryComparison/types"

const props = defineProps<{
    fieldValue: CategoryComparisonValue
    screenType: "mobile" | "tablet" | "desktop"
    indexBlock?: number | string
    emptyStateMessage?: string
}>()

const layout = inject("layout", {}) as any

const families = computed<ComparisonFamily[]>(() => props.fieldValue?.families ?? [])

const currentFamily = computed(() => families.value.find(family => family.is_current))

const highlightIndex = computed(() => families.value.findIndex(family => family.is_current))

const itemsOf = (family?: ComparisonFamily): ComparisonTemplate =>
    family?.category_comparison?.items ?? {}

const baseTemplate = computed<ComparisonTemplate>(() => {
    const templateName =
        props.fieldValue?.settings?.template ??
        currentFamily.value?.category_comparison?.template ??
        families.value[0]?.category_comparison?.template

    return templateName && templateName in COMPARISON_TEMPLATES
        ? COMPARISON_TEMPLATES[templateName]
        : {}
})

const isFilled = (value?: string | null) => typeof value === "string" && value.trim() !== ""

const cellOf = (family: ComparisonFamily | undefined, key: string): ComparisonTemplateItem | null => {
    const item = itemsOf(family)[key]

    if (!item || item.show === false) {
        return null
    }

    return item
}

const valueOf = (family: ComparisonFamily, key: string) => {
    const value = cellOf(family, key)?.value

    return isFilled(value) ? (value as string) : null
}

const rows = computed(() => {
    const orderedKeys = [
        ...Object.keys(baseTemplate.value),
        ...families.value.flatMap(family => Object.keys(itemsOf(family))),
    ]

    const uniqueKeys = [...new Set(orderedKeys)]

    return uniqueKeys
        .map(key => ({
            key,
            label:
                cellOf(currentFamily.value, key)?.label ??
                families.value.map(family => itemsOf(family)[key]?.label).find(isFilled) ??
                baseTemplate.value[key]?.label ??
                key.replaceAll("_", " "),
        }))
        .filter(row => families.value.some(family => valueOf(family, row.key)))
})

const hasComparison = computed(() => families.value.length > 0 && rows.value.length > 0)

const gridTemplateColumns = computed(() => {
    const labelColumn = props.screenType === "mobile" ? "120px" : "minmax(120px, 0.7fr)"

    return `${labelColumn} repeat(${families.value.length}, minmax(150px, 1fr))`
})

const totalRows = computed(() => rows.value.length + 2)

const containerStyle = computed(() => ({
    ...getStyles(layout?.app?.webpage_layout?.container?.properties, props.screenType),
    ...getStyles(props.fieldValue?.container?.properties, props.screenType),
    width: "auto",
}))

const colorTokens = computed(() => ({
    "--comparison-highlight": props.fieldValue?.settings?.highlight_color ?? "#f1efe3",
    "--comparison-label": props.fieldValue?.settings?.label_color ?? "#8a7b3d",
    "--comparison-link": props.fieldValue?.settings?.link_color ?? "#4b7f6b",
    "--comparison-value": props.fieldValue?.settings?.value_color ?? "#6b7280",
}))
</script>

<template>
    <div
        :id="fieldValue?.id ? fieldValue.id : 'category-comparison-' + indexBlock"
        component="category-comparison"
        :style="containerStyle"
        class="px-[10px] py-6 sm:px-[50px]"
    >
        <div class="mb-6 text-center">
            <div v-if="fieldValue?.title" class="text-3xl font-semibold text-gray-800" v-html="fieldValue.title" />
            <div v-else class="text-3xl font-semibold text-gray-800">
                {{ ctrans("Range Comparison") }}
            </div>
        </div>

        <div v-if="hasComparison" class="w-full overflow-x-auto">
            <div class="grid min-w-max items-stretch" :style="{ gridTemplateColumns, ...colorTokens }">
                <div
                    v-if="highlightIndex >= 0"
                    class="rounded-2xl"
                    :style="{
                        gridColumn: highlightIndex + 2,
                        gridRow: `1 / span ${totalRows}`,
                        backgroundColor: 'var(--comparison-highlight)',
                    }"
                />

                <div :style="{ gridColumn: 1, gridRow: 1 }" />

                <div
                    :style="{ gridColumn: 1, gridRow: 2 }"
                    class="flex items-center px-3 py-3 text-[11px] font-bold uppercase tracking-wide"
                >
                    <span :style="{ color: 'var(--comparison-label)' }">{{ ctrans("Family") }}</span>
                </div>

                <div
                    v-for="(row, rowIndex) in rows"
                    :key="`label-${row.key}`"
                    :style="{ gridColumn: 1, gridRow: rowIndex + 3 }"
                    class="flex items-center px-3 py-3 text-[11px] font-bold uppercase tracking-wide"
                >
                    <span :style="{ color: 'var(--comparison-label)' }">{{ row.label }}</span>
                </div>

                <template v-for="(family, familyIndex) in families" :key="`family-${familyIndex}`">
                    <div :style="{ gridColumn: familyIndex + 2, gridRow: 1 }" class="relative px-4 pb-2 pt-4">
                        <div
                            class="mx-auto aspect-square w-full max-w-[150px] overflow-hidden rounded-xl"
                            :class="family.is_current ? 'bg-white' : ''"
                        >
                            <Image
                                :src="family.image"
                                :alt="family.name"
                                class="h-full w-full object-contain"
                            />
                        </div>
                    </div>

                    <div
                        :style="{ gridColumn: familyIndex + 2, gridRow: 2 }"
                        class="relative flex items-start justify-center px-4 py-3 text-center text-sm leading-5"
                    >
                        <LinkIris
                            v-if="family.url && !family.is_current"
                            :href="family.url"
                            class="underline underline-offset-4 hover:opacity-80"
                            :style="{ color: 'var(--comparison-link)' }"
                        >
                            {{ family.name }}
                        </LinkIris>

                        <span v-else :style="{ color: 'var(--comparison-value)' }">
                            {{ family.name }}
                        </span>
                    </div>

                    <div
                        v-for="(row, rowIndex) in rows"
                        :key="`cell-${familyIndex}-${row.key}`"
                        :style="{ gridColumn: familyIndex + 2, gridRow: rowIndex + 3 }"
                        class="relative flex items-center justify-center px-4 py-3 text-center text-xs leading-5"
                    >
                        <span
                            v-if="valueOf(family, row.key)"
                            :style="{ color: family.is_current ? 'var(--comparison-value)' : 'var(--comparison-link)' }"
                        >
                            {{ valueOf(family, row.key) }}
                        </span>

                        <span
                            v-else
                            class="block h-px w-10 bg-gray-400"
                            :aria-label="ctrans('Not available')"
                        />
                    </div>
                </template>
            </div>
        </div>

        <div
            v-else-if="emptyStateMessage"
            class="rounded-xl border border-dashed border-gray-300 px-4 py-10 text-center text-sm text-gray-400"
        >
            {{ emptyStateMessage }}
        </div>
    </div>
</template>
