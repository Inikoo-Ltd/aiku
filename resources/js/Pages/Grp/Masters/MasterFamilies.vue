<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 13 Sept 2025 12:59:35 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import TableMasterFamilies from "@/Components/Tables/Grp/Goods/TableMasterFamilies.vue"
import { capitalize } from "@/Composables/capitalize"
import { ref, computed } from "vue"
import { faShapes, faSortAmountDownAlt, faBrowser, faSortAmountDown, faHome } from '@fal'
import { library } from "@fortawesome/fontawesome-svg-core"
import { PageHeadingTypes } from '@/types/PageHeading'
import FormCreateMasterFamily from "@/Components/Master/FormCreateMasterFamily.vue"
import { trans } from 'laravel-vue-i18n'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { routeType } from '@/types/route'
import { useTabChange } from '@/Composables/tab-change'
import Tabs from "@/Components/Navigation/Tabs.vue"
import Modal from '@/Components/Utils/Modal.vue'
import ListSelector from "@/Components/DepartmentAndFamily/ListSelector.vue"
import { notify } from '@kyvg/vue3-notification'

library.add(faShapes, faSortAmountDownAlt, faBrowser, faSortAmountDown, faHome)

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    tabs: {
        current: string
        navigation: {}
    }
    index?: {}
    sales?: {}
    shopsData: {}
    currency: {}
    storeRoute: routeType
    routes?: {}
    hideCheckbox?: boolean
    accessedFromCollection?: boolean
}>()

const showDialog = ref(false)

const isModalOpen = {
    families: ref(false),
}
const isLoading = ref<string | boolean>(false)
const errorMessage = ref<string>('')

const currentTab = ref<string>(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)
const currentData = computed(() => (props as any)[currentTab.value])

const component = computed(() => {
    const components: any = {
        index: TableMasterFamilies,
        sales: TableMasterFamilies,
    }

    return components[currentTab.value]
})

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
    selectedIds: any[],
    resetSelection: () => void
}) => {
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
                text: trans(`Successfully attach :tscope.`, { tscope: scope }),
                type: 'success',
            })
            resetSelection()
        },
        onError: (errors: any) => {
            errorMessage.value = errors
            notify({
                title: trans('Something went wrong.'),
                text: trans(`Failed to attach :tscope, please try again.`, { tscope: scope }),
                type: 'error',
            })
        },
        onFinish: () => {
            isLoading.value = false
        }
    })
}

const selectedFamiliesId = ref<number[]>([])
const resetSelectionByScope = {
    families: () => (selectedFamiliesId.value = []),
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #button-add-master-family>
            <Button :label="trans('Master Family')" @click="showDialog = true" :style="'create'" />
        </template>
        <template #other v-if="accessedFromCollection">
            <Button
                type="secondary"
                label="Attach Families"
                icon="fal fa-plus"
                @click="isModalOpen.families.value = true"
                :tooltip="trans('Attach families to this collections')"
            />
            <Modal
                :isOpen="isModalOpen.families.value"
                @onClose="isModalOpen.families.value = false"
                width="w-full max-w-6xl h-full"
            >
                <ListSelector
                    :headLabel="`${trans('Add families to collection')}`"
                    :routeFetch="routes.dataList"
                    :isLoadingSubmit="isLoading"
                    @submit="(ids) =>
                        onSubmitAttach({
                            closeModal: () => (isModalOpen.families.value = false),
                            scope: 'families',
                            routeToSubmit: routes.submitAttach,
                            selectedIds: ids,
                            resetSelection: resetSelectionByScope.families,
                        })
                    "
                />
            </Modal>
        </template>
    </PageHeading>
    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
    <component :isCheckBox="!hideCheckbox" :is="component" :key="currentTab" :tab="currentTab" :data="currentData" :routes="routes ?? []"></component>
    <FormCreateMasterFamily
        :showDialog="showDialog"
        :storeProductRoute="storeRoute"
        @update:show-dialog="(value) => showDialog = value"
        :shopsData="shopsData"
    />
</template>