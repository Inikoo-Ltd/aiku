<script setup lang="ts">
import { ref } from "vue"
import draggable from "vuedraggable"
import Image from "@/Components/Image.vue"
import Dialog from "primevue/dialog"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faExclamationCircle } from "@fas"

const props = defineProps<{
  selectedWeblock: String
  webBlockTypes: {
    data: Array<any>
  }
}>()

const emits = defineEmits<{
  (e: 'pick-block', value: object): void
}>()

// State untuk dialog manual
const showDialog = ref(false)
const pendingElement = ref<any>(null)

const onPick = (element: any) => {
  if (props.selectedWeblock === element.code) return

  pendingElement.value = element
  showDialog.value = true
}

const confirmPick = () => {
  emits("pick-block", pendingElement.value)
  showDialog.value = false
  pendingElement.value = null
}

const cancelPick = () => {
  showDialog.value = false
  pendingElement.value = null
}

// Disable drag
const allowMove = () => false
</script>

<template>
  <!-- Dialog konfirmasi -->
  <Dialog v-model:visible="showDialog" header="Confirm Change" modal :closable="false" style="width: 450px">
    <p class="mb-2">
      <FontAwesomeIcon :icon="faExclamationCircle" class="text-yellow-500 mr-2" />
      {{ trans('Are you sure you want to switch to this template?') }}
    </p>
    <p class="text-sm text-red-500 mb-4 ">
      {{ trans('This action will replace all current content and you will lose any unsaved changes.') }}
    </p>


    <div class="flex justify-end gap-2 border-t pt-4">
      <Button label="Cancel" type="secondary" @click="cancelPick" />
      <Button label="Yes, Change" type="primary" @click="confirmPick" />
    </div>
  </Dialog>

  <!-- List blok -->
  <draggable :list="webBlockTypes.data" ghost-class="ghost" group="column" itemKey="id" class="mt-4 space-y-4"
    :move="allowMove" :disabled="true">
    <template #item="{ element }">
      <div @click="() => onPick(element)" :class="[
        'relative bg-white border rounded shadow-sm overflow-hidden cursor-pointer',
        selectedWeblock === element.code
          ? 'border-indigo-500 ring-2 ring-indigo-300'
          : 'border-gray-200'
      ]">

        <!-- Label Beta Test -->
        <div v-if="element.is_in_test"
          class="absolute top-2 left-2 bg-yellow-500 text-white text-[10px] font-bold px-2 py-1 rounded">
          BETA TEST
        </div>

        <!-- Gambar -->
        <div class="w-full bg-gray-100  flex items-center justify-center overflow-hidden">
          <Image :imageCover="true" :src="element.screenshot"  :alt="`Screenshot of ${element.name}`" />
        </div>


        <!-- Nama -->
        <div class="text-xs text-center py-2 font-bold text-gray-700">
          {{ element.code }}
        </div>
      </div>
    </template>
  </draggable>

</template>

<style scoped lang="scss">
.ghost {
  opacity: 0.5;
  background-color: #e2e8f0;
  border: 2px dashed #4F46E5;
}
</style>
