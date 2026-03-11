<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import { faShapes, faSortAmountDownAlt, faBrowser, faSortAmountDown, faHome } from '@fal'
import { library } from "@fortawesome/fontawesome-svg-core"
import { PageHeadingTypes } from '@/types/PageHeading'
import TableMasterCollections from '@/Components/Tables/Grp/Goods/TableMasterCollections.vue'
import { useTabChange } from '@/Composables/tab-change'
import Tabs from "@/Components/Navigation/Tabs.vue"
import { computed, ref } from "vue"
import { trans } from 'laravel-vue-i18n'
import { routeType } from '@/types/route'
import { notify } from '@kyvg/vue3-notification'
import Button from '@/Components/Elements/Buttons/Button.vue'
import Modal from '@/Components/Utils/Modal.vue'
import ListSelector from "@/Components/DepartmentAndFamily/ListSelector.vue"

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
    data: {}
    accessedFromCollection?: boolean
    routes?: {}
}>()

const currentTab = ref<string>(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)
const currentData = computed(() => {
    if (currentTab.value === 'index' || currentTab.value === 'sales') {
        return (props as any)[currentTab.value] || props.data
    }
    return props.data
})

const component = computed(() => {
    const components: any = {
        index: TableMasterCollections,
        sales: TableMasterCollections,
    }

    return components[currentTab.value]
})

const isModalOpen = {
    collections: ref(false),
}
const isLoading = ref<string | boolean>(false)
const errorMessage = ref<string>('')


const onSubmitAttach = async ({
    closeModal,
    scope,
    routeToSubmit,
    selectedIds,
    resetSelection
}: {
    closeModal: () => void,
    scope: 'products' | 'families' | 'collections',
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

const selectedCollectionId = ref<number[]>([])
const resetSelectionByScope = {
    collections: () => (selectedCollectionId.value = []),
}

</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #other v-if="accessedFromCollection">
            <Button
                type="secondary"
                label="Attach Collections"
                icon="fal fa-plus"
                @click="isModalOpen.collections.value = true"
                :tooltip="trans('Link another collection to this collections')"
            />
            <!-- Modal: Collections -->
            <Modal
                :isOpen="isModalOpen.collections.value"
                @onClose="isModalOpen.collections.value = false"
                width="w-full max-w-6xl"
            >
                <ListSelector
                    :headLabel="`${trans('Add collections to collection')}`"
                    :routeFetch="routes.dataList"
                    :isLoadingSubmit="isLoading"
                    @submit="(ids) =>
                        onSubmitAttach({
                            closeModal: () => (isModalOpen.collections.value = false),
                            scope: 'collections',
                            routeToSubmit: routes.submitAttach,
                            selectedIds: ids,
                            resetSelection: resetSelectionByScope.collections,
                        })
                    "
                />
            </Modal>
        </template>
    </PageHeading>

    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />

    <component
        :is="component"
        :key="currentTab"
        :tab="currentTab"
        :data="currentData"
        :routes="routes"
        :hideDelete="true"
    ></component>
</template>