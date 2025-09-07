<!--
  -  Author: Jonathan Lopez <raul@inikoo.com>
  -  Created: Wed, 12 Oct 2022 16:50:56 Central European Summer Time, Benalmádena, Malaga,Spain
  -  Copyright (c) 2022, Jonathan Lopez
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import TableMasterFamilies from "@/Components/Tables/Grp/Goods/TableMasterFamilies.vue"
import { capitalize } from "@/Composables/capitalize"
import { ref } from "vue"
import { faShapes, faSortAmountDownAlt, faBrowser, faSortAmountDown, faHome } from '@fal'
import { library } from "@fortawesome/fontawesome-svg-core"
import { PageHeading as PageHeadingTypes } from '@/types/PageHeading'
library.add(faShapes, faSortAmountDownAlt, faBrowser, faSortAmountDown, faHome)
import FormCreateMasterFamily from "@/Components/Master/FormCreateMasterFamily.vue"
import { trans } from 'laravel-vue-i18n'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { routeType } from '@/types/route'

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    data: {}
    shopsData: {}
    currency: {}
    storeRoute: routeType
}>()

const showDialog = ref(false)
const dummyMasterProductCategory = 312
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #button-add-master-family>
            <Button :label="trans('Master family')" @click="showDialog = true" :style="'create'" />
        </template>
    </PageHeading>
    <TableMasterFamilies :data="data" />
    <FormCreateMasterFamily
        :showDialog="showDialog" 
        :storeProductRoute="storeRoute" 
        @update:show-dialog="(value) => showDialog = value"
        :shopsData="shopsData"
    />
</template>