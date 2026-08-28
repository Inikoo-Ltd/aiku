<!--
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed } from "vue"
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import FieldForm from "@/Components/Forms/FieldForm.vue"
import MasterAnomalyBlocks from "@/Components/Masters/MasterAnomalyBlocks.vue"
import CompositionTriangle from "@/Components/Goods/CompositionTriangle.vue"
import { capitalize } from "@/Composables/capitalize"
import { routeType } from "@/types/route"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faAtom, faMoneyBill } from "@fal"

library.add(faAtom, faMoneyBill)

// The composition workflow is one continuous decision (trade units → packing → price),
// so unlike EditModel there is no section navigation: everything is on one scroll.
const props = defineProps<{
    title: string
    pageHead: object
    anomalies?: {
        items: {
            product_id: number
            shop_code: string
            shop_slug: string
            url: string
            issues: string[]
            ignored_issues: string[]
        }[]
        fixRoute: routeType
        killRebelRoute: routeType
    } | null
    formData: {
        blueprint: {
            label: string
            icon: string
            accent?: string
            compact?: boolean
            fields: Record<string, any>
        }[]
        args: {
            updateRoute: routeType
        }
    }
}>()

// The triangle feeds off whatever trade-units field the blueprint carries; the rows
// already hold quantity/packed_in on the product and master pages.
const tradeUnitsField = computed(() => {
    for (const section of props.formData.blueprint) {
        if (section.fields?.trade_units) return section.fields.trade_units
    }
    return null
})

const triangleTradeUnits = computed(() => tradeUnitsField.value?.value ?? [])

const triangleMood = computed(() => {
    const items = props.anomalies?.items ?? []
    if (items.some(item => item.issues.length)) return 'crying'
    if (items.some(item => item.ignored_issues.length)) return 'angry'
    return null
})
const triangleProductsCount = computed(() => {
    const context = tradeUnitsField.value?.productsContext
    if (!context) return undefined
    return Object.values(context).reduce((total: number, products: any) => total + products.length, 0)
})
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="px-4 py-5 sm:px-6 lg:px-8 grid gap-8 xl:grid-cols-[minmax(0,64rem)_minmax(16rem,20rem)]">
        <div class="flex flex-col gap-y-5 min-w-0">
            <MasterAnomalyBlocks :anomalies="anomalies" />

            <!-- accent 'pink' marks the sell side, the colour the triangle's TU—P edge wears -->
            <section v-for="(section, sectionIdx) in formData.blueprint" :key="sectionIdx"
                class="rounded-lg border bg-white"
                :class="section.accent === 'pink' ? 'border-pink-300' : 'border-gray-200'">
                <div class="border-b px-4 py-2 sm:px-6 flex items-center gap-2"
                    :class="section.accent === 'pink' ? 'border-pink-100' : 'border-gray-100'">
                    <FontAwesomeIcon v-if="section.icon" :icon="section.icon" fixed-width aria-hidden="true"
                        :class="section.accent === 'pink' ? 'text-pink-400' : 'text-gray-400'" />
                    <h2 class="font-medium" :class="section.accent === 'pink' ? 'text-pink-700' : 'text-gray-700'">{{ section.label }}</h2>
                </div>
                <div class="px-4 sm:px-6" :class="section.compact ? 'py-2 divide-y divide-gray-100' : 'py-4'">
                    <FieldForm v-for="(fieldData, fieldName) in section.fields"
                        :key="`${sectionIdx}-${fieldName}`"
                        :field="fieldName as string"
                        :fieldData="fieldData"
                        :args="formData.args"
                        :refForms="undefined"
                    />
                </div>
            </section>
        </div>

        <aside class="hidden xl:block">
            <div class="sticky top-8">
                <CompositionTriangle
                    v-if="triangleTradeUnits.length"
                    :tradeUnits="triangleTradeUnits"
                    :productsCount="triangleProductsCount"
                    :mood="triangleMood"
                />
            </div>
        </aside>
    </div>
</template>
