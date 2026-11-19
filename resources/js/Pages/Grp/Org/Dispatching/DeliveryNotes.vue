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
import { faLayerGroup, faTags, faTasksAlt, faChartPie, faPaperPlane, faHourglassHalf, faUserCheck, faHandPaper, faBoxCheck, faBoxOpen, faCheckDouble, faTasks } from "@fal"
import TableDeliveryNotes from "@/Components/Tables/Grp/Org/Dispatching/TableDeliveryNotes.vue"
import HasPickTableDeliveryNote from '@/Components/Tables/Grp/Org/Dispatching/HasPickTableDeliveryNote.vue'
import { ref, inject, computed, reactive } from "vue"
import { layoutStructure } from "@/Composables/useLayoutStructure";
import { routeType } from '@/types/route'
import { ctrans } from '@/Composables/useTrans'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'

library.add(faLayerGroup, faTags, faTasksAlt, faChartPie, faPaperPlane, faHourglassHalf, faUserCheck, faHandPaper, faBoxCheck, faBoxOpen, faCheckDouble, faTasks)

const props = defineProps<{
  pageHead: PageHeadingTypes
  title: string
  data?: {}
  todo?: Boolean
  shopType?: string
  picking_session_route?: routeType
  bucket?: string
  truck?: { route: routeType; trucks: { id: number; partner: string; label: string }[]; notes: Record<string, { reference: string; partner: string | null; truck: string | null; truck_url: string | null }> } | null
}>()

const truckSelection = ref<Record<string, boolean>>({})
const truckNoteIds = computed(() => Object.keys(truckSelection.value).filter((id) => truckSelection.value[id]).map(Number))
const truckPartners = computed(() => [...new Set(truckNoteIds.value.map((id) => props.truck?.notes[id]?.partner ?? '?'))])
const notesOnTrucks = computed(() =>
  Object.entries(props.truck?.notes ?? {})
    .filter(([, note]) => note.truck)
    .reduce<Record<string, string[]>>((byTruck, [, note]) => ({ ...byTruck, [note.truck as string]: [...(byTruck[note.truck as string] ?? []), note.reference] }), {})
)
const isTruckLocked = (row: { id: number }) => {
  const note = props.truck?.notes[row.id]
  if (!note || note.truck) return true
  const ticked = truckPartners.value.filter((partner) => partner !== '?')
  return ticked.length > 0 && !ticked.includes(note.partner ?? '?') && !truckSelection.value[row.id]
}
const isTruckDialogOpen = ref(false)
const truckChoice = ref<number>(0)
const truckError = ref<string | null>(null)
const isPuttingOnTruck = ref(false)

function putOnTruck() {
  if (!props.truck) return
  router.post(
    route(props.truck.route.name, props.truck.route.parameters),
    { delivery_note_ids: truckNoteIds.value, partner_shipment_id: truckChoice.value || null },
    {
      onStart: () => {
        isPuttingOnTruck.value = true
        truckError.value = null
      },
      onError: (errors) => (truckError.value = Object.values(errors).join(' ')),
      onFinish: () => (isPuttingOnTruck.value = false),
    }
  )
}

const selectedDeliveryNotes = reactive(new Set<number>())
const loading=ref(false)

// const pickingSessionRoute = {
//   name: props.picking_session_route.name,
//   parameters: props.picking_session_route.parameters,
// }

function createPickingSession() {
  if (selectedDeliveryNotes.size === 0) return

  if (!props.picking_session_route) {
    notify({
      title: ctrans('Something went wrong'),
      text: ctrans('Please try again or contact support.'),
      type: 'error',
    })
    return
  }

  loading.value = true

  router.post(
    route(props.picking_session_route.name, props.picking_session_route.parameters),
    { delivery_notes: [...selectedDeliveryNotes] },
    {
      onFinish: () => {
        loading.value = false
      },
      onError: (errors) => {
        loading.value = false
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


const isHidden = computed(() => { 
  return props.shopType == 'b2b' || props.shopType == 'external'
})

</script>

<template>
  <Head :title="capitalize(title)" />
  <PageHeading :data="pageHead">
    <template #other>
        <Button
        v-if="!isHidden && todo"
        type="create"
        :label="ctrans('Picking session') + ' ('+ selectedDeliveryNotes.size + ')'"
        :loading="loading"
        :disabled="selectedDeliveryNotes.size <= 0"
        @click="createPickingSession"
      />
      <Button
        v-if="truck"
        type="secondary"
        :icon="faLayerGroup"
        :label="ctrans('Consolidate') + ' (' + truckNoteIds.length + ')'"
        :disabled="truckNoteIds.length === 0 || truckPartners.length > 1"
        @click="(isTruckDialogOpen = true), (truckChoice = 0), (truckError = null)"
      />
    </template>
  </PageHeading>
  <HasPickTableDeliveryNote v-if="todo" :data="data" :selectedDeliveryNotes="selectedDeliveryNotes"/>
  <p v-if="truck && truckPartners.length > 1" class="mx-4 mt-3 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
    {{ ctrans('These notes are for :partners: a consolidated delivery note goes to one partner. Untick the ones for the other partner.', { partners: truckPartners.join(' and ') }) }}
  </p>
  <p v-if="truck && Object.keys(notesOnTrucks).length" class="mx-4 mt-3 text-xs text-gray-500">
    {{ ctrans('A consolidated delivery note goes to one partner: once you tick a note, only that partner\'s notes can be ticked. Already consolidated:') }}
    {{ Object.entries(notesOnTrucks).map(([truckReference, notes]) => notes.join(', ') + ' on ' + truckReference).join('; ') }}
  </p>
  <TableDeliveryNotes
    v-if="!todo"
    :data="data"
    :bucket
    :selectable="!!truck"
    :disabledCheckbox="(row: { id: number }) => isTruckLocked(row)"
    :truckOf="(row: { id: number }) => (truck?.notes[row.id]?.truck ? { reference: truck.notes[row.id].truck as string, url: truck.notes[row.id].truck_url as string } : null)"
    @selectRow="(rows) => (truckSelection = { ...rows })"/>

  <Dialog v-if="truck" v-model:visible="isTruckDialogOpen" modal :header="ctrans('Consolidate')" :style="{ width: '26rem', maxWidth: 'calc(100vw - 2rem)' }" :draggable="false">
    <div class="space-y-3 text-sm">
      <p>{{ ctrans(':count delivery notes go on', { count: String(truckNoteIds.length) }) }}</p>
      <Select
        v-model="truckChoice"
        :options="[{ id: 0, label: ctrans('A new consolidated delivery note') }, ...truck.trucks.filter((option) => truckPartners.includes(option.partner))]"
        optionLabel="label"
        optionValue="id"
        class="w-full"
      />
      <p class="text-xs text-gray-500">{{ ctrans('Only consolidations still open are listed: no customs invoices yet, not dispatched. The notes must all be for the same partner.') }}</p>
      <p v-if="truckError" class="text-xs text-red-500">{{ truckError }}</p>
      <div class="flex justify-end gap-2">
        <Button type="tertiary" :label="ctrans('Cancel')" @click="isTruckDialogOpen = false" />
        <Button type="secondary" :icon="faLayerGroup" :label="ctrans('Consolidate')" :loading="isPuttingOnTruck" @click="putOnTruck" />
      </div>
    </div>
  </Dialog>
</template>
