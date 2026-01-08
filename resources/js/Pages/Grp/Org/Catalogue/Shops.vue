<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Wed, 12 Oct 2022 16:50:56 Central European Summer Time, Benalmádena, Malaga,Spain
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { router, Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import TableShops from "@/Components/Tables/Grp/Org/Catalogue/TableShops.vue"
import { capitalize } from "@/Composables/capitalize"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { computed, ref } from "vue"
import { library } from "@fortawesome/fontawesome-svg-core"

import TableDepartments from "@/Components/Tables/Grp/Org/Catalogue/TableDepartments.vue"
import TableFamilies from "@/Components/Tables/Grp/Org/Catalogue/TableFamilies.vue"
import TableProducts from "@/Components/Tables/Grp/Org/Catalogue/TableProducts.vue"
import { useTabChange } from "@/Composables/tab-change"
import { faCube, faFolder, faFolderTree } from '@fal'
import { PageHeadingTypes } from "@/types/PageHeading"
import Button from "@/Components/Elements/Buttons/Button.vue";
import PureMultiselectInfiniteScroll from "@/Components/Pure/PureMultiselectInfiniteScroll.vue";
import Modal from "@/Components/Utils/Modal.vue";
import { trans } from "laravel-vue-i18n";
import { useLayoutStore } from "@/Stores/layout";
import ButtonWithLink from '@/Components/Elements/Buttons/ButtonWithLink.vue'
import PureMultiselect from '@/Components/Pure/PureMultiselect.vue'

library.add( faCube, faFolder, faFolderTree )

const props = defineProps<{
    pageHead: PageHeadingTypes
    tabs: {
        current: string
        navigation: {}
    }
    title: string
    shops?: {}
    departments?: {}
    families?: {}
    products?: {}

}>()

const layout = useLayoutStore()
const isCreateShopModal = ref(false)
const engines = ref([])
const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {

    const components = {
        shops: TableShops,
        departments: TableDepartments,
        families: TableFamilies,
        products: TableProducts,
    }
    return components[currentTab.value]

})

const redirectToTarget = (engine: string) => {
    switch (engine) {
        case 'aiku':
            router.visit(route('grp.org.shops.create', route().params))
            break
        default:
            router.visit(route('grp.org.shops.external.create', {...route().params, engine: engine}))
    }
}

// Section: create internal shop
const isLoadingVisitInternal = ref(false)
const isCreateShopModalInternal = ref(false)
const selectedMasterShopForInternal = ref(null)
const createInternalShop = () => {
    router.visit(
        route('grp.masters.master_shops.show.shop.create', {
            masterShop: selectedMasterShopForInternal.value,
            organisation: layout.currentParams?.organisation
        }),
        {
            onStart: () => {
                isLoadingVisitInternal.value = true
            },
            onError: () => {
                isLoadingVisitInternal.value = false
            }
        }
    )
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">

        <template #button-shop="{action}">
            <div>
                <Button
                    v-if="layout.app.environment !== 'production'"
                    type="primary"
                    :style="'create'"
                    :label="trans('Internal')"
                    @click="() => isCreateShopModalInternal = true"
                    xrouteTarget="{
                        name: 'grp.masters.master_shops.show.shop.create',
                        parameters: {
                            
                        }
                    }"
                    class="rounded-r-none border-r-0"
                />
                <Button
                    type="primary"
                    :style="'create'"
                    :label="trans('External')"
                    @click="() => {
                        isCreateShopModal = true
                        engines = action?.options
                    }"
                    class="rounded-l-none border-l-0"
                />
            </div>
        </template>

    </PageHeading>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <component :is="component" :tab="currentTab" :data="props[currentTab]" />


    <Modal :isOpen="isCreateShopModalInternal" width="w-full max-w-lg" @close="isCreateShopModalInternal = false">
        <div>
            <div class="font-bold text-2xl text-center mb-4">
                {{ trans("Create Shop") }}
            </div>

            <div class="">
                {{ trans("Select master shop for the new shop") }}:
            </div>

            <div>
                <PureMultiselectInfiniteScroll
                    v-model="selectedMasterShopForInternal"
                    :fetchRoute="{
                        name: 'grp.masters.master_shops.index'
                    }"
                    labelProp="name"
                    valueProp="slug"
                    placeholder="Select shop"
                    required
                />
            </div>

            <div class="mt-6">
                <Button
                    @click="() => createInternalShop()"
                    v-tooltip="selectedMasterShopForInternal ? '' : 'Select master shop'"
                    :label="trans('Create shop')"
                    :loading="isLoadingVisitInternal"
                    :disabled="!selectedMasterShopForInternal"
                    iconRight="far fa-arrow-right"
                    full
                />
            </div>
        </div>
    </Modal>

    <Modal :isOpen="isCreateShopModal" @onClose="isCreateShopModal = false" width="w-full max-w-[500px]">
        <div class="text-center font-semibold text-lg mb-4">
            {{ trans("Select shop type to create:") }}
        </div>

        <div class="flex justify-center gap-2">
            <Button v-for="engine in engines" :key="engine"
                :label="engine.label"
                type="tertiary"
                @click="redirectToTarget(engine.value)"
            />
        </div>
    </Modal>
</template>
