<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Wed, 22 Feb 2023 12:20:38 Central European Standard Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import { PageHeading as PageHeadingTypes } from '@/types/PageHeading'
import { library } from "@fortawesome/fontawesome-svg-core"
import Button from '@/Components/Elements/Buttons/Button.vue'
import { faTags, faTasksAlt, faChartPie, faPaperPlane, faHourglassHalf, faUserCheck, faHandPaper, faBoxCheck, faBoxOpen, faCheckDouble, faTasks } from "@fal"
import TableDeliveryNotes from "@/Components/Tables/Grp/Org/Dispatching/TableDeliveryNotes.vue"
import HasPickTableDeliveryNote from '@/Components/Tables/Grp/Org/Dispatching/HasPickTableDeliveryNote.vue'
import { ref, inject } from "vue"
import { layoutStructure } from "@/Composables/useLayoutStructure";
import { routeType } from '@/types/route'

library.add(faTags, faTasksAlt, faChartPie, faPaperPlane, faHourglassHalf, faUserCheck, faHandPaper, faBoxCheck, faBoxOpen, faCheckDouble, faTasks)

const props = defineProps<{
  pageHead: PageHeadingTypes
  title: string
  data?: {}
  todo?: Boolean
  picking_session_route : routeType
}>()

const selectedDeliveryNotes = ref<number[]>([])
const layoutStore = inject("layout", layoutStructure);

console.log("layoutStore", layoutStore)
</script>

<template>
  <Head :title="capitalize(title)" />
  <PageHeading :data="pageHead">
    <template #other>
      <Link v-if="selectedDeliveryNotes.length > 0" :href="route(picking_session_route.name,picking_session_route.parameters)" method="post" as="button" :data="{ delivery_notes : selectedDeliveryNotes}">
        <Button type="create" label="picking session" />
      </Link>
    </template>
  </PageHeading>
  <HasPickTableDeliveryNote v-if="todo" :data="data" v-model:selectedDeliveryNotes="selectedDeliveryNotes"/>
  <TableDeliveryNotes v-else :data="data" />
</template>
