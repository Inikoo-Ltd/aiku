<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 27 Jan 2024 15:13:18 Malaysia Time, Sanur, Bali, Indonesia
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { notify } from "@kyvg/vue3-notification"
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import TablePalletReturns from "@/Components/Tables/Grp/Org/Fulfilment/TablePalletReturns.vue"
import TablePalletReturnItemUploads from "@/Components/Tables/TablePalletReturnItemUploads.vue"
import HasPickTablePalletReturns from "@/Components/Tables/Grp/Org/Fulfilment/HasPickTablePalletReturns.vue"
import Button from '@/Components/Elements/Buttons/Button.vue'
import { PageHeadingTypes } from '@/types/PageHeading'
import Tabs from "@/Components/Navigation/Tabs.vue"
import { computed, ref } from "vue"
import type { Component } from "vue"
import type { Navigation } from "@/types/Tabs"
import { useTabChange } from "@/Composables/tab-change"
import { faSeedling } from '@fal'
import {library} from "@fortawesome/fontawesome-svg-core";
import { routeType } from '@/types/route'
import { trans } from 'laravel-vue-i18n'

library.add(faSeedling)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    data: {}
    tabs: {
        current: string
        navigation: Navigation
    }
    returns?: {}
    uploads?: {}
    todo?: boolean
    picking_session_route?: routeType
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const selectedPalletReturns = ref<number[]>([])
const loading = ref(false)

const component = computed(() => {
    const components: Component = {
        returns: TablePalletReturns,
        uploads: TablePalletReturnItemUploads
    }

    return components[currentTab.value]
})

const showPickingSessionButton = computed(() => {
    return (props.todo ?? false) && currentTab.value === 'returns' && selectedPalletReturns.value.length > 0
})

function createPickingSession() {
    if (selectedPalletReturns.value.length === 0) return

    if (!props.picking_session_route) {
        notify({
            title: trans('Something went wrong'),
            text: trans('Please try again or contact support.'),
            type: 'error',
        })
        return
    }

    loading.value = true

    router.post(
        route(props.picking_session_route.name, props.picking_session_route.parameters),
        { pallet_returns: selectedPalletReturns.value },
        {
            onFinish: () => {
                loading.value = false
            },
            onError: (errors) => {
                loading.value = false
                if (errors.message) {
                    notify({
                        title: trans('Validation Error'),
                        text: errors.message,
                        type: 'error',
                    })
                }
            },
        }
    )
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #other>
            <Button
                v-if="showPickingSessionButton"
                type="create"
                :label="trans('Picking session')"
                :loading="loading"
                @click="createPickingSession"
            />
        </template>
    </PageHeading>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <HasPickTablePalletReturns
        v-if="(props.todo ?? false) && currentTab === 'returns'"
        :tab="currentTab"
        :data="props[currentTab]"
        v-model:selectedPalletReturns="selectedPalletReturns"
    />
    <component
        v-else
        :is="component"
        :tab="currentTab"
        :data="props[currentTab]"
    ></component>
</template>
