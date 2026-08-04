<!--
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed } from "vue"
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import FieldForm from "@/Components/Forms/FieldForm.vue"
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
    formData: {
        blueprint: {
            label: string
            icon: string
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
        <div class="flex flex-col gap-y-8 min-w-0">
            <section v-for="(section, sectionIdx) in formData.blueprint" :key="sectionIdx"
                class="rounded-lg border border-gray-200 bg-white">
                <div class="border-b border-gray-100 px-4 py-3 sm:px-6 flex items-center gap-2">
                    <FontAwesomeIcon v-if="section.icon" :icon="section.icon" class="text-gray-400" fixed-width aria-hidden="true" />
                    <h2 class="font-medium text-gray-700">{{ section.label }}</h2>
                </div>
                <div class="px-4 py-4 sm:px-6">
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
                />
            </div>
        </aside>
    </div>
</template>
