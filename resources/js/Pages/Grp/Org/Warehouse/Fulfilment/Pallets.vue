<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 23 May 2024 15:57:55 British Summer Time, Sheffield, UK
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import TablePallets from "@/Components/Tables/Grp/Org/Inventory/Fulfilment/TablePallets.vue"
import Action from "@/Components/Forms/Fields/Action.vue"
import { useForm } from '@inertiajs/vue3'
import { PageHeadingTypes } from "@/types/PageHeading"
import { library } from "@fortawesome/fontawesome-svg-core"

import { faStickyNote, } from '@fal'
library.add( faStickyNote, )

const props = defineProps<{
    data: {}
    title: string
    pageHead: PageHeadingTypes
}>()

const form = useForm({ pallet: [] })

</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
    </PageHeading>
    <TablePallets :data="data" :tab="'pallets'" :form="form" :dataToSubmit="form.data()" />
</template>
