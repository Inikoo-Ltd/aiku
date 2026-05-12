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
import { PageHeadingTypes } from '@/types/PageHeading'
import { library } from "@fortawesome/fontawesome-svg-core"
import Button from '@/Components/Elements/Buttons/Button.vue'
import { faTags, faTasksAlt, faChartPie, faPaperPlane, faHourglassHalf, faUserCheck, faHandPaper, faBoxCheck, faBoxOpen, faCheckDouble, faTasks } from "@fal"
import TableDeliveryNotes from "@/Components/Tables/Grp/Org/Dispatching/TableDeliveryNotes.vue"
import HasPickTableDeliveryNote from '@/Components/Tables/Grp/Org/Dispatching/HasPickTableDeliveryNote.vue'
import { ref, inject, computed, onMounted } from "vue"
import { layoutStructure } from "@/Composables/useLayoutStructure";
import { routeType } from '@/types/route'
import { trans } from 'laravel-vue-i18n'
import { Dialog } from 'primevue'
import axios from 'axios'

library.add(faTags, faTasksAlt, faChartPie, faPaperPlane, faHourglassHalf, faUserCheck, faHandPaper, faBoxCheck, faBoxOpen, faCheckDouble, faTasks)

const props = defineProps<{
  pageHead: PageHeadingTypes
  title: string
  data?: {}
  shopType: string
}>()

const layoutStore = inject("layout", layoutStructure);
const loading=ref(false)

const isOpenModalCreateReturn = ref(false)

onMounted(async () => {
  await axios.get(route('grp.json.delivery_note_valid_for_return', {
    warehouse:route().params['warehouse'],
  }))
  .then((res) => {
    console.info("Res:", res)
  });
  
})

</script>

<template>
  <Head :title="capitalize(title)" />
  <PageHeading :data="pageHead">
    <template #other>
    </template>
    <template #button-create-return="{ action }">
      <Button 
        :type="action.type"
        :style="action.style"
        :label="action.label"
        :icon="action.icon"
        @click="() => isOpenModalCreateReturn = true"
      />
      {{ isOpenModalCreateReturn }}
    </template>
  </PageHeading>
  <TableDeliveryNotes :data="data" />
  <Dialog
    v-model:visible="isOpenModalCreateReturn"
    modal 
    closable
    dismissableMask
    :showHeader="false" 
    :style="{ width: '50rem' }" 
    @hide="() => {
      isOpenModalCreateReturn = false
    }"
  >
    <div class="pt-3 pb-2 font-semibold ">
      {{ trans("Create Return") }}
    </div>
    <div>
      {{ 'Make an <option> here, only able to select 1 valid delivery note for Return creation. Use this route for fetch:' }}
      <br> <br>
      <pre>
        grp.json.delivery_note_valid_for_return
      </pre>
      {{ 'After selecting a valid route, hit this route to create a new return by hitting this route:' }}
      <br> <br>
      <pre>
        {
          name: 'grp.models.delivery_note.return.process',
          parameters: {
            deliveryNote: delivery_note.id
          },
          method: 'patch'
        }
      </pre>
      {{ 'Redirect to that return after success' }}
    </div>
    <div class="flex">
      <Button 
        class="ml-auto"
      >
        {{ trans("Create") }}
      </Button>
    </div>
  </Dialog>
</template>
