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
import ComparisonFamilySelect from "@/Iris/Components/BlocksUtils/CategoryComparison/ComparisonFamilySelect.vue"
import { DEFAULT_NUMBER_OF_FAMILIES } from "@/Iris/Components/BlocksUtils/CategoryComparison/useComparisonFamilies"
import type {
    CategoryComparisonValue,
    ComparisonFamily,
    ScreenType,
} from "@/Iris/Components/BlocksUtils/CategoryComparison/types"

const props = defineProps<{
    fieldValue: CategoryComparisonValue
    screenType: ScreenType
    indexBlock?: number | string
    emptyStateMessage?: string
    families: ComparisonFamily[]
    familyOptions?: ComparisonFamily[]
    selectedSlugs?: string[]
    maxComparedFamilies?: number
    loading?: boolean
}>()

const emits = defineEmits<{
    (e: "toggleFamily", slug: string): void
}>()

const layout = inject("layout", {}) as any

const comparedFamilies = computed<ComparisonFamily[]>(() => props.families ?? [])

const currentFamily = computed(() => comparedFamilies.value.find(family => family.is_current))

const highlightIndex = computed(() => comparedFamilies.value.findIndex(family => family.is_current))

const itemsOf = (family?: ComparisonFamily): ComparisonTemplate =>
    family?.category_comparison?.items ?? {}

const baseTemplate = computed<ComparisonTemplate>(() => {
    const templateName =
        props.fieldValue?.settings?.template ??
        currentFamily.value?.category_comparison?.template ??
        comparedFamilies.value[0]?.category_comparison?.template

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
        ...comparedFamilies.value.flatMap(family => Object.keys(itemsOf(family))),
    ]

    const uniqueKeys = [...new Set(orderedKeys)]

    return uniqueKeys.map(key => ({
        key,
        label:
            cellOf(currentFamily.value, key)?.label ??
            comparedFamilies.value.map(family => itemsOf(family)[key]?.label).find(isFilled) ??
            baseTemplate.value[key]?.label ??
            key.replaceAll("_", " "),
    }))
})

const hasComparison = computed(() => comparedFamilies.value.length > 0 && rows.value.length > 0)

const canPickFamilies = computed(() => (props.familyOptions?.length ?? 0) > 1)

const hasSomethingToShow = computed(
    () => props.loading || hasComparison.value || Boolean(props.emptyStateMessage)
)

const RESPONSIVE_CLASSES = {
    mobile: {
        block: "px-[10px] py-4",
        title: "mb-4 text-xl",
        labelColumn: "minmax(78px, 0.6fr)",
        label: "px-2 py-2 text-[9px]",
        familyName: "px-2 py-2 text-xs leading-4",
        cell: "px-2 py-2 text-[11px] leading-4",
        imageFrame: "px-2 pb-1 pt-2",
        image: "max-w-[90px]",
    },
    tablet: {
        block: "px-[24px] py-5",
        title: "mb-5 text-2xl",
        labelColumn: "minmax(100px, 0.7fr)",
        label: "px-3 py-2.5 text-[10px]",
        familyName: "px-3 py-2.5 text-[13px] leading-[18px]",
        cell: "px-3 py-2.5 text-xs leading-[18px]",
        imageFrame: "pb-2 pt-3",
        image: "max-w-[120px]",
    },
    desktop: {
        block: "px-[50px] py-6",
        title: "mb-6 text-3xl",
        labelColumn: "minmax(120px, 0.7fr)",
        label: "px-3 py-3 text-[11px]",
        familyName: "px-4 py-3 text-sm leading-5",
        cell: "px-4 py-3 text-xs leading-5",
        imageFrame: "pb-2 pt-4",
        image: "max-w-[150px]",
    },
}

const responsive = computed(() => RESPONSIVE_CLASSES[props.screenType] ?? RESPONSIVE_CLASSES.desktop)

const gridTemplateColumns = computed(
    () => `${responsive.value.labelColumn} repeat(${comparedFamilies.value.length}, minmax(0, 1fr))`
)

const totalRows = computed(() => rows.value.length + 2)

const containerStyle = computed(() => ({
    ...getStyles(layout?.app?.webpage_layout?.container?.properties, props.screenType),
    ...getStyles(props.fieldValue?.container?.properties, props.screenType),
    width: "auto",
}))

const SKELETON_ROWS = 6

const skeletonColumnCount = computed(() => DEFAULT_NUMBER_OF_FAMILIES[props.screenType])

const skeletonColumns = computed(() => `repeat(${skeletonColumnCount.value}, minmax(0, 1fr))`)

const skeletonCells = computed(() => skeletonColumnCount.value * SKELETON_ROWS)

const colorTokens = computed(() => ({
    "--comparison-highlight": props.fieldValue?.settings?.highlight_color ?? "#f1efe3",
    "--comparison-label": props.fieldValue?.settings?.label_color ?? "#8a7b3d",
    "--comparison-link": props.fieldValue?.settings?.link_color ?? "black",
    "--comparison-value": props.fieldValue?.settings?.value_color ?? "black",
}))


</script>

<template>
    <div
        v-if="hasSomethingToShow"
        :id="fieldValue?.id ? fieldValue.id : 'category-comparison-' + indexBlock"
        component="category-comparison"
        :style="containerStyle"
        :class="responsive.block"
    >
        <div class="text-center" :class="responsive.title">
            <div v-if="fieldValue?.title" class="font-semibold text-gray-800" v-html="fieldValue.title" />
            <div v-else class="font-semibold text-gray-800">
                {{ ctrans("Range Comparison") }}
            </div>
        </div>

        <div v-if="loading" class="grid animate-pulse gap-3" :style="{ gridTemplateColumns: skeletonColumns }">
            <div v-for="skeletonCell in skeletonCells" :key="skeletonCell" class="h-10 rounded-lg bg-gray-200" />
        </div>

        <div v-else-if="hasComparison">
            <div class="w-full overflow-x-auto">
                <div class="grid w-full items-stretch" :style="{ gridTemplateColumns, ...colorTokens }">
                    <div
                        v-if="highlightIndex >= 0"
                        class="rounded-2xl"
                        :style="{
                            gridColumn: highlightIndex + 2,
                            gridRow: `1 / span ${totalRows}`,
                            backgroundColor: 'var(--comparison-highlight)',
                        }"
                    />

                    <div
                        :style="{ gridColumn: 1, gridRow: 1 }"
                        class="flex items-end"
                        :class="responsive.imageFrame"
                    >
                        <ComparisonFamilySelect
                            v-if="canPickFamilies"
                            :options="familyOptions ?? []"
                            :selectedSlugs="selectedSlugs ?? []"
                            :max="maxComparedFamilies ?? 0"
                            :screenType="screenType"
                            @toggle="slug => emits('toggleFamily', slug)"
                        />
                    </div>

                    <div
                        :style="{ gridColumn: 1, gridRow: 2 }"
                        class="flex items-center font-bold uppercase tracking-wide"
                        :class="responsive.label"
                    >
                        <span :style="{ color: 'var(--comparison-label)' }">{{ ctrans("Family") }}</span>
                    </div>

                    <div
                        v-for="(row, rowIndex) in rows"
                        :key="`label-${row.key}`"
                        :style="{ gridColumn: 1, gridRow: rowIndex + 3 }"
                        class="flex items-center font-bold uppercase tracking-wide"
                        :class="responsive.label"
                    >
                        <span :style="{ color: 'var(--comparison-label)' }">{{ row.label }}</span>
                    </div>

                    <template v-for="(family, familyIndex) in comparedFamilies" :key="`family-${familyIndex}`">
                        <div :style="{ gridColumn: familyIndex + 2, gridRow: 1 }" class="relative" :class="responsive.imageFrame">
                            <div
                                class="mx-auto aspect-square w-full overflow-hidden rounded-xl"
                                :class="[responsive.image, family.is_current ? 'bg-white' : '']"
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
                            class="relative flex items-start justify-center text-center"
                            :class="responsive.familyName"
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
                            class="relative flex items-center justify-center text-center"
                            :class="responsive.cell"
                        >
                            <span
                                :style="{
                                    color: valueOf(family, row.key)
                                        ? (family.is_current ? 'var(--comparison-value)' : 'var(--comparison-link)')
                                        : 'var(--comparison-value)',
                                }"
                            >
                                {{ valueOf(family, row.key) ?? "-" }}
                            </span>
                        </div>
                    </template>
                </div>
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
