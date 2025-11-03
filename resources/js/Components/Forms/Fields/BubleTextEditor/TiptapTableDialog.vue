<script setup lang="ts">
import { ref, computed } from "vue"
import Dialog from "primevue/dialog"
import InputNumber from "primevue/inputnumber"
import InputSwitch from "primevue/inputswitch"
import Button from "primevue/button"

const props = defineProps<{
  show: boolean
}>()

const emit = defineEmits<{
  (e: "close"): void
  (e: "insert", table: { rows: number; columns: number; withHeader: boolean }): void
}>()

// reactive bridge for v-model:visible
const visible = computed({
  get: () => props.show,
  set: (val) => {
    if (!val) emit("close")
  },
})

const inputColumnsRef = ref<number>(3)
const inputRowsRef = ref<number>(3)
const inputWithHeaderRef = ref<boolean>(true)

function onSubmit() {
  emit("insert", {
    rows: inputRowsRef.value,
    columns: inputColumnsRef.value,
    withHeader: inputWithHeaderRef.value,
  })
  emit("close")
}
</script>

<template>
  <Dialog
    header="Create Table"
    v-model:visible="visible"
    modal
    :closable="false"
    style="width: 450px"
    class="p-fluid"
  >
    <form @submit.prevent="onSubmit" class="space-y-5">
      <div class="flex flex-row gap-4">
        <div class="flex-1">
          <Label for="input-table-columns">Columns</Label>
          <InputNumber
            id="input-table-columns"
            v-model="inputColumnsRef"
            inputClass="w-full"
            :min="1"
            showButtons
          />
        </div>

        <div class="flex-1">
          <Label for="input-table-rows">Rows</Label>
          <InputNumber
            id="input-table-rows"
            v-model="inputRowsRef"
            inputClass="w-full"
            :min="1"
            showButtons
          />
        </div>
      </div>

      <div class="flex items-center gap-3">
        <InputSwitch v-model="inputWithHeaderRef" inputId="with-header" />
        <Label for="with-header" class="text-sm text-gray-700 select-none">
          Table Header
        </Label>
      </div>

      <div class="flex justify-end gap-3 pt-3">
        <Button
          label="Cancel"
          text
          severity="secondary"
          @click="emit('close')"
        />
        <Button
          label="Create"
          icon="pi pi-check"
          type="submit"
        />
      </div>
    </form>
  </Dialog>
</template>
