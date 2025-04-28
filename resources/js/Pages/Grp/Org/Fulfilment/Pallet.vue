<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Mon, 17 Oct 2022 17:33:07 British Summer Time, Sheffield, UK
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import TableStoredItems from "@/Components/Tables/Grp/Org/Fulfilment/TableStoredItems.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { computed, ref } from "vue"
import type { Component } from "vue"
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue"
import { useTabChange } from "@/Composables/tab-change"
import PalletShowcase from "@/Components/Showcases/Org/PalletShowcase.vue"
import { PageHeading as PageHeadingTypes } from '@/types/PageHeading'
import { Tabs as TSTabs } from '@/types/Tabs'
import StockItemsMovements from '@/Components/Showcases/Grp/StockItemsMovements.vue'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faExchange, faFragile, faNarwhal } from '@fal'
import TableStoredItemsInWarehouse from '@/Components/Tables/Grp/Org/Fulfilment/TableStoredItemsInWarehouse.vue'
import ModalConfirmation from '@/Components/Utils/ModalConfirmation.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { trans } from 'laravel-vue-i18n'
import ModalAfterConfirmationDelete from '@/Components/Utils/ModalAfterConfirmationDelete.vue'
import ModalSupervisorList from '@/Components/Utils/ModalSupervisorList.vue'

library.add(faFragile, faNarwhal, faExchange)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tabs: TSTabs
    pallet: {}
    list_stored_items?: {}

    stored_items?: {}
    history?: {}
    movements?: {}
    showcase: {}
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)
const component = computed(() => {
    const components: Component = {
        showcase: PalletShowcase,
        stored_items: TableStoredItemsInWarehouse,
        movements: StockItemsMovements,
        history: TableHistories
    }
    return components[currentTab.value]

})

</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #button-return-pallet="{ action }">
            <!-- {{ action }} -->
            <ModalConfirmation
                :routeYes="action.route"
                :title="trans(`Return pallet ${pallet.data?.reference} to customer?`)"
                :description="trans(`The pallet ${pallet.data?.reference} will be set as returned to the customer, and no longer exist in warehouse. This action cannot be reverse.`)"
            >
                <template #default="{ changeModel }">
                    <Button
                        @click="() => changeModel()"
                        :label="trans('Set pallet as returned')"
                        type="secondary"
                    />
                </template>

                <template #btn-yes="{ isLoadingdelete, clickYes}">
                    <Button
                        :loading="isLoadingdelete"
                        @click="() => clickYes()"
                        :label="trans('Yes, return the pallet')"
                    />
                </template>
            </ModalConfirmation>
        </template>

        <template #button-delete-booked-in="{ action }">
            <div>
                <template v-if="action.supervisor">
                <ModalAfterConfirmationDelete
                    :routeDelete="action.route"
                    :data="pallet?.data"
                    isFullLoading
                    isWithMessage
                >
                    <template #default="{ isOpenModal, changeModel }">

                        <Button
                            @click="() => changeModel()"
                            :style="action.style"
                            :label="action.label"
                            :icon="action.icon"
                            :iconRight="action.iconRight"
                            :key="`ActionButton${action.label}${action.style}`"
                            :tooltip="action.tooltip"
                        />

                    </template>
                </ModalAfterConfirmationDelete>
                </template>
                <template v-else>
                    <ModalSupervisorList
                    :routeDelete="action.route"
                    :routeSupervisor="action.supervisors_route"
                    isFullLoading
                    isWithMessage
                >
                    <template #default="{ isOpenModal, changeModel }">

                        <Button
                            @click="() => changeModel()"
                            :style="action.style"
                            :label="action.label"
                            :icon="action.icon"
                            :iconRight="action.iconRight"
                            :key="`ActionButton${action.label}${action.style}`"
                            :tooltip="action.tooltip"
                        />

                    </template>
                    </ModalSupervisorList>
                </template>
            </div>
        </template>

    </PageHeading>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <component :is="component" :data="props[currentTab]" :tab="currentTab" :list_stored_items></component>
</template>
