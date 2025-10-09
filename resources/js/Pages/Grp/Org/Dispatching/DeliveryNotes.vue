<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Wed, 22 Feb 2023 12:20:38 Central European Standard Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { notify } from "@kyvg/vue3-notification"
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
import { trans } from 'laravel-vue-i18n'

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
const loading=ref(false)

// const pickingSessionRoute = {
//   name: props.picking_session_route.name,
//   parameters: props.picking_session_route.parameters,
// }

function createPickingSession() {
  if (selectedDeliveryNotes.value.length === 0) return

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
    { delivery_notes: selectedDeliveryNotes.value },
    {
      onFinish: () => {
        loading.value = false
      },
      onError: (errors) => {
        loading.value = false
        console.log(errors.message)
        if (errors.message) {
          notify({
            title: 'Validation Error',
            text: errors.message,
            type: 'error',
          })
        }
      },
    }
  )
}

console.log("layoutStore", layoutStore)
</script>

<template>
  <Head :title="capitalize(title)" />
  <PageHeading :data="pageHead">
    <template #other>
        <Button
        v-if="selectedDeliveryNotes.length > 0"
        type="create"
        label="picking session"
        :loading="loading"
        @click="createPickingSession"
      />
    </template>
  </PageHeading>
  <HasPickTableDeliveryNote v-if="todo" :data="data" v-model:selectedDeliveryNotes="selectedDeliveryNotes"/>
  <TableDeliveryNotes v-else :data="data" />
</template>
