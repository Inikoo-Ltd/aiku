<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import type { Component } from 'vue'

import PageHeading from '@/Components/Headings/PageHeading.vue'
import Tabs from '@/Components/Navigation/Tabs.vue'
import { useTabChange } from '@/Composables/tab-change'
import { capitalize } from '@/Composables/capitalize'
import { trans } from 'laravel-vue-i18n'
import { notify } from '@kyvg/vue3-notification'

import {
    PageHeading as PageHeadingTypes
} from '@/types/PageHeading'
import { Tabs as TSTabs } from '@/types/Tabs'
import { routeType } from '@/types/route'

import CollectionsShowcase from '@/Components/Dropshipping/Catalogue/CollectionsShowcase.vue'
import TableFamilies from '@/Components/Tables/Grp/Org/Catalogue/TableFamilies.vue'
import TableProducts from '@/Components/Tables/Grp/Org/Catalogue/TableProducts.vue'
import ListSelector from "@/Components/Departement&Family/ListSelector.vue"
import Button from '@/Components/Elements/Buttons/Button.vue'
import Modal from '@/Components/Utils/Modal.vue'

import { faPlus } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import TableCollections from '@/Components/Tables/Grp/Org/Catalogue/TableCollections.vue'
library.add(faPlus)

const props = defineProps<{
    title: string,
    pageHead: PageHeadingTypes
    tabs: TSTabs
    showcase?: { stats: {} }
    families?: {}
    products?: {}
    collections?: {}
    routes: {
        families: { dataList: routeType, submitAttach: routeType, detach: routeType }
        products: { dataList: routeType, submitAttach: routeType, detach: routeType }
        collections: { dataList: routeType, submitAttach: routeType, detach: routeType }
    }
}>()

const currentTab = ref(props.tabs.current)
const isLoading = ref<string | boolean>(false)
const errorMessage = ref<string>('')

const selectedFamiliesId = ref<number[]>([])
const selectedProductsId = ref<number[]>([])

const isModalOpen = {
    families: ref(false),
    products: ref(false),
}

const handleTabUpdate = (tabSlug: string) => {
    useTabChange(tabSlug, currentTab)
    errorMessage.value = ''
}

const component = computed(() => {
    const components: Record<string, Component> = {
        showcase: CollectionsShowcase,
        families: TableFamilies,
        products: TableProducts,
        collections: TableCollections,
    }
    return components[currentTab.value]
})

const resetSelectionByScope = {
    products: () => (selectedProductsId.value = []),
    families: () => (selectedFamiliesId.value = []),
}

const onSubmitAttach = async ({
    closeModal,
    scope,
    routeToSubmit,
    selectedIds,
    resetSelection
}: {
    closeModal: () => void,
    scope: 'products' | 'families',
    routeToSubmit: routeType,
    selectedIds: any[], // <- bisa array of object atau number, kita amankan dulu
    resetSelection: () => void
}) => {
    // Pastikan ambil id saja dari setiap item
    const ids = selectedIds.map(item => typeof item === 'object' ? item.id : item)

    if (!ids.length) return
    isLoading.value = 'submitAttach'

    router.post(route(routeToSubmit.name, routeToSubmit.parameters), {
        [scope]: ids
    }, {
        preserveScroll: true,
        onSuccess: () => {
            closeModal()
            notify({
                title: trans('Success'),
                text: trans(`Successfully attach ${scope}.`),
                type: 'success',
            })
            resetSelection()
        },
        onError: (errors: any) => {
            errorMessage.value = errors
            notify({
                title: trans('Something went wrong.'),
                text: trans(`Failed to attach ${scope}, please try again.`),
                type: 'error',
            })
        },
        onFinish: () => {
            isLoading.value = false
        }
    })
}

</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #other>
            <section v-if="currentTab == 'families'">
                <Button
                    type="secondary"
                    label="Attach families"
                    icon="fal fa-plus"
                    @click="isModalOpen.families.value = true"
                    :tooltip="trans('Attach families to this collections')"
                />
            </section>
            <section v-if="currentTab == 'products'">
                <Button
                    type="secondary"
                    label="Attach products"
                    icon="fal fa-plus"
                    @click="isModalOpen.products.value = true"
                    :tooltip="trans('Attach products to this collections')"
                />
            </section>
        </template>
    </PageHeading>

    <Tabs
        :current="currentTab"
        :navigation="tabs.navigation"
        @update:tab="handleTabUpdate"
    />

    <component
        :is="component"
        :data="props[currentTab as keyof typeof props]"
        :tab="currentTab"
        :routes="props.routes[currentTab]"
    />

    <Modal
        :isOpen="isModalOpen.products.value"
        @onClose="isModalOpen.products.value = false"
        width="w-full max-w-6xl"
    >
        <ListSelector
            :headLabel="`${trans('Add products to collection')}`"
            :routeFetch="routes.products.dataList"
            :isLoadingSubmit="isLoading"
            @submit="(ids) =>
                onSubmitAttach({
                    closeModal: () => (isModalOpen.products.value = false),
                    scope: 'products',
                    routeToSubmit: routes.products.submitAttach,
                    selectedIds: ids,
                    resetSelection: resetSelectionByScope.products,
                })
            "
        />
    </Modal>

    <Modal
        :isOpen="isModalOpen.families.value"
        @onClose="isModalOpen.families.value = false"
        width="w-full max-w-6xl h-full"
    >
        <ListSelector
            :headLabel="`${trans('Add families to collection')}`"
            :routeFetch="routes.families.dataList"
            :isLoadingSubmit="isLoading"
            @submit="(ids) =>
                onSubmitAttach({
                    closeModal: () => (isModalOpen.families.value = false),
                    scope: 'families',
                    routeToSubmit: routes.families.submitAttach,
                    selectedIds: ids,
                    resetSelection: resetSelectionByScope.families,
                })
            "
        />
    </Modal>
</template>
