<!--
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import FieldForm from "@/Components/Forms/FieldForm.vue"
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
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="max-w-5xl px-4 py-5 sm:px-6 lg:px-8 flex flex-col gap-y-8">
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
</template>
