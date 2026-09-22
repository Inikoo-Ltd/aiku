<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 23 May 2024 09:45:43 British Summer Time, Sheffield, UK
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import Table from '@/Components/Table/Table.vue'
import Icon from "@/Components/Icon.vue"
import Button from '@/Components/Elements/Buttons/Button.vue'
import Popover from 'primevue/popover';
import { ref, inject } from 'vue'
import { trans } from "laravel-vue-i18n"
import PureTextarea from '@/Components/Pure/PureTextarea.vue';
import { layoutStructure } from '@/Composables/useLayoutStructure';
import ToggleSwitch from 'primevue/toggleswitch';
import { notify } from '@kyvg/vue3-notification';

import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheck, faRobot, faStickyNote } from '@fal'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"



library.add(faRobot)

const props = defineProps<{
    data: {}
    state: string
    tab?: string
}>()
// const isActionLoading = ref<string | boolean>(false)
const emits = defineEmits<{
    (e: 'renderTableKey'): void
}>()

const _op = ref()
const notes = ref("")
const permanent = ref(true)
const layout = inject('layout', layoutStructure)
const event = (event) => {
    _op.value.toggle(event)
}

const addNotes = () => {
    // console.log({ user: layout.user.username, note: notes.value, permanent: permanent.value}, route(props.data.routeStore))
    // TODO: need details value for text or html
    router.post(route('grp.models.fulfilment_customer_note.store', [
        route().params["fulfilmentCustomer"]
    ]),
        { details: JSON.stringify({ text: "description" }), note: notes.value, permanent: permanent.value },
        {   
            onSuccess: () => {
                console.log('Note added successfully!');
                _op.value.hide()
                notes.value = ""
            },
            onError: (error) => {
                notify({
                    title: "Failed to add new notes",
                    text: error.message ? error.message : 'failed to create notes',
                    type: "error",
                })
            },
        });
};


</script>

<template>
    <div>
      <Table :resource="data" :name="tab">
        <template #add-on-button>
          <Button 
            label="Add Note" 
            type="create" 
            size="xs" 
            :icon="faStickyNote" 
            class="bg-blue-500 hover:bg-blue-600 text-white"
            @click="event" 
          />
          <Popover ref="_op">
            <div class="flex flex-col gap-4 w-[20rem] pt-3">
              <!-- Toggle Switch -->
              <div class="flex items-center justify-between">
                <div class="text-gray-600 font-medium">{{ trans("Permanent :")}}</div>
                <ToggleSwitch v-model="permanent">
                  <template #handle="{ checked }">
                    <FontAwesomeIcon 
                      :icon="checked && faCheck" 
                      class="text-green-500 text-xs" fixed-width 
                    />
                  </template>
                </ToggleSwitch>
              </div>
              <!-- Notes Input -->
              <PureTextarea 
                :rows="4" 
                v-model="notes" 
                class="border rounded-lg p-2 w-full text-gray-700"
                placeholder="Enter your note here..." 
              />
              <!-- Save Button -->
              <div class="flex justify-end">
                <Button 
                  type="save" 
                  size="xs" 
                  :disabled="notes.length === 0" 
                  class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg"
                  @click="addNotes" 
                />
              </div>
            </div>
          </Popover>
        </template>
      </Table>
    </div>
  </template>
  