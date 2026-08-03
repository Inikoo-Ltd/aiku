<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Fri, 24 Feb 2023 10:21:46 Central European Standard Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { library } from '@fortawesome/fontawesome-svg-core'
import {
    faBoxUsd,
    faCameraRetro,
    faClipboard,
    faClock,
    faMoneyBill,
    faPaperclip,
    faPaperPlane,
    faPersonDolly,
    faPoop,
    faTruckContainer
} from '@fal'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import Tabs from '@/Components/Navigation/Tabs.vue'
import SupplierShowcase from '@/Components/Showcases/Grp/SupplierShowcase.vue'
import TableHistories from '@/Components/Tables/Grp/Helpers/TableHistories.vue'
import { useTabChange } from '@/Composables/tab-change'
import { capitalize } from '@/Composables/capitalize'

library.add(
    faBoxUsd,
    faCameraRetro,
    faClipboard,
    faClock,
    faMoneyBill,
    faPaperclip,
    faPaperPlane,
    faPersonDolly,
    faPoop,
    faTruckContainer
)

const props = defineProps<{
    title: string
    pageHead: object
    tabs: {
        current: string
        navigation: object
    }
    showcase?: object
    history?: object
    errors?: object
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
    const components = {
        showcase: SupplierShowcase,
        history: TableHistories
    }

    return components[currentTab.value]
})

const getErrors = () => {
    if (props.errors.purchase_order) {
        if (confirm(props.errors.purchase_order)) {
            const form = useForm({ force: true })

            form.post(route(
                props.pageHead.create_direct.route.name,
                props.pageHead.create_direct.route.parameters
            ))
        }
    }
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <div v-if="props.errors.purchase_order">{{ getErrors() }}</div>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <component :is="component" :data="props[currentTab]" :tab="currentTab" />
</template>
