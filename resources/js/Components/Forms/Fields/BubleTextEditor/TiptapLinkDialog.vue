<script setup lang="ts">
import { ref, watch } from "vue"
import Dialog from "primevue/dialog"
import InputText from "primevue/inputtext"
import Button from "primevue/button"

const props = defineProps<{ show: boolean; currentUrl?: string }>()
const emit = defineEmits(["close", "update"])

const inputLinkRef = ref<string>("")
const visible = ref(props.show)

watch(
  () => props.show,
  (val) => (visible.value = val)
)

watch(
  () => props.currentUrl,
  (url) => (inputLinkRef.value = url ?? "")
)

function closeDialog() {
  visible.value = false
  emit("close")
}

function update() {
  emit("update", inputLinkRef.value)
  closeDialog()
}
</script>

<template>
  <Dialog
    v-model:visible="visible"
    header="Link"
    modal
    class="w-full max-w-md"
    @hide="closeDialog"
    :contentStyle="{overflowY : 'visible'}"
  >
    <form @submit.prevent="update" class="flex flex-col space-y-5">
      <div>
        <label for="input-link-url" class="block text-sm text-gray-600 mb-2 select-none">
          Link
        </label>
        <InputText
          id="input-link-url"
          v-model="inputLinkRef"
          type="url"
          class="w-full"
          placeholder="https://example.com"
        />
      </div>

      <div class="flex justify-end gap-3">
        <Button
          label="Cancel"
          text
          severity="secondary"
          @click="closeDialog"
        />
        <Button
          label="Save"
          icon="pi pi-check"
          type="submit"
        />
      </div>
    </form>
  </Dialog>
</template>
