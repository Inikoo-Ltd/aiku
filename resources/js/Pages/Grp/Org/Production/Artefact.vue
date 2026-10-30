<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { ctrans } from "@/Composables/useTrans"
import PageHeading from '@/Components/Headings/PageHeading.vue'
import Tabs from "@/Components/Navigation/Tabs.vue"

import { useTabChange } from "@/Composables/tab-change"
import { capitalize } from "@/Composables/capitalize"
import { computed, defineAsyncComponent, ref } from 'vue'
import type { Component } from 'vue'

import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue"
import { PageHeadingTypes } from '@/types/PageHeading'
import { Tabs as TSTabs } from '@/types/Tabs'
import TableArtefactRecipe from "@/Components/Tables/Grp/Org/Production/TableArtefactRecipe.vue"
import TableArtefactCompliance from "@/Components/Tables/Grp/Org/Production/TableArtefactCompliance.vue"
import TableArtefactBatches from "@/Components/Tables/Grp/Org/Production/TableArtefactBatches.vue"
import ArtefactShowcase from "@/Components/Showcases/Grp/ArtefactShowcase.vue"
import ArtefactLabels from "@/Components/Production/Artefact/ArtefactLabels.vue"
import ArtisanAssignments from "@/Components/Production/ArtisanAssignments.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faUserLock, faCodeBranch } from "@fal"
import ModalAssignArtefactToCustomer from "@/Components/Production/Artefact/ModalAssignArtefactToCustomer.vue"

library.add(faUserLock, faCodeBranch)

const props = defineProps<{
    title: string,
    pageHead: PageHeadingTypes
    tabs: TSTabs
    showcase?: {}
    current_orders?: {}
    batches?: {}
    manufacture_tasks?:{}
    labels?: {}
    compliance?: {}
    history?: {}
    artisans: {}
    customer_product?: { artefact_id: number, shops: Array<{ id: number, slug: string, code: string, name: string }> } | null
    exclusive_products?: Array<{ code: string, customer: string, route: { name: string, parameters: string[] } }>


}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {

    const components: Component = {
        showcase: ArtefactShowcase,
        current_orders: TableArtefactBatches,
        batches: TableArtefactBatches,
        manufacture_tasks: TableArtefactRecipe,
        labels: ArtefactLabels,
        compliance: TableArtefactCompliance,
        history: TableHistories,
    }

    return components[currentTab.value]

})

</script>


<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #other>
            <ModalAssignArtefactToCustomer v-if="customer_product" :artefact-id="customer_product.artefact_id" :shops="customer_product.shops" />
        </template>
    </PageHeading>
    <div v-for="product in exclusive_products" :key="product.code" class="mx-4 mt-3 flex items-center gap-2 rounded border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-800">
        <FontAwesomeIcon icon="fal fa-user-lock" fixed-width aria-hidden="true" />
        <span>{{ ctrans("Custom product :code, sold only to", { code: product.code }) }}</span>
        <Link :href="route(product.route.name, product.route.parameters)" class="font-semibold underline">{{ product.customer }}</Link>
    </div>
    <ArtisanAssignments :data="artisans" />
    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />

    <component :is="component" :data="props[currentTab as keyof typeof props]" :tab="currentTab" />
</template>