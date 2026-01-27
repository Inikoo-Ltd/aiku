<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 13 Sept 2025 12:59:35 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
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
}>()

const showDialog = ref(false)

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

</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #button-add-master-family>
            <Button :label="trans('Master Family')" @click="showDialog = true" :style="'create'" />
        </template>
    </PageHeading>
    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
    <component :is="component" :key="currentTab" :tab="currentTab" :data="currentData"></component>
    <FormCreateMasterFamily
        :showDialog="showDialog"
        :storeProductRoute="storeRoute"
        @update:show-dialog="(value) => showDialog = value"
        :shopsData="shopsData"
    />
</template>