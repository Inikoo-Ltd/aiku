<script setup lang="ts">
import { ref } from "vue"
import draggable from "vuedraggable"
import Image from "@common/Components/Image.vue"
import Dialog from "primevue/dialog"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faExclamationCircle } from "@fas"
import { faCheck, faTimes } from "@fal"

const props = defineProps<{
  selectedWeblock: String
  webBlockTypes: {
    data: Array<any>
  }
}>()

const emits = defineEmits<{
  (e: 'pick-block', value: object): void
}>()

const showDialog = ref(false)
const pendingElement = ref<any>(null)

const onPick = (element: any) => {
  if (props.selectedWeblock === element.code) return

  if (!props.selectedWeblock) {
    emits("pick-block", element)
    return
  }

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

const allowMove = () => false
</script>

<template>
  <Dialog v-model:visible="showDialog" :header="ctrans('Change template')" modal :closable="false" style="width: 28rem">
    <p class="mb-2">
      <FontAwesomeIcon :icon="faExclamationCircle" class="text-yellow-500 mr-2" fixed-width />
      {{ ctrans('Are you sure you want to switch to this template?') }}
    </p>
    <p class="text-sm text-red-500 mb-4 ">
      {{ ctrans('This action will replace all current content and you will lose any unsaved changes.') }}
    </p>


    <div class="flex justify-end gap-2 border-t pt-4">
      <Button :label="ctrans('Cancel')" type="tertiary" @click="cancelPick">
        <template #icon>
          <FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
        </template>
      </Button>
      <Button :label="ctrans('Yes, change')" type="tertiary" @click="confirmPick">
        <template #icon>
          <FontAwesomeIcon :icon="faExclamationCircle" class="text-amber-500" fixed-width aria-hidden="true" />
        </template>
      </Button>
    </div>
  </Dialog>

  <draggable :list="webBlockTypes.data" ghost-class="ghost" group="column" itemKey="id" class="mt-4 space-y-4"
    :move="allowMove" :disabled="true">
    <template #item="{ element }">
      <div @click="() => onPick(element)" :class="[
        'relative bg-white border rounded shadow-sm overflow-hidden cursor-pointer transition',
        selectedWeblock === element.code
          ? 'border-[var(--app-accent)] ring-2 ring-[var(--app-accent-muted)]'
          : 'border-gray-200 hover:border-[var(--app-accent)]'
      ]">

        <div v-if="element.is_in_test"
          class="absolute top-2 left-2 z-10 bg-amber-500 text-white text-[10px] font-bold px-2 py-1 rounded uppercase">
          {{ ctrans('Beta') }}
        </div>

        <div v-if="selectedWeblock === element.code"
          class="absolute top-2 right-2 z-10 flex items-center gap-1 rounded bg-[var(--app-accent)] px-2 py-1 text-[10px] font-bold text-[var(--app-accent-text)]">
          <FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" />
          {{ ctrans('In use') }}
        </div>

        <div class="w-full bg-gray-100  flex items-center justify-center overflow-hidden">
          <Image :imageCover="true" :src="element.screenshot"  :alt="`Screenshot of ${element.name}`" />
        </div>


        <div class="py-2 text-center">
          <div class="text-xs font-bold text-gray-700">{{ element.name || element.code }}</div>
          <div v-if="element.name && element.name !== element.code" class="whitespace-nowrap text-[10px] text-gray-400">{{ element.code }}</div>
        </div>
      </div>
    </template>
  </draggable>

</template>

<style scoped lang="scss">
.ghost {
  opacity: 0.5;
  background-color: #e2e8f0;
  border: 2px dashed var(--app-accent);
}
</style>
