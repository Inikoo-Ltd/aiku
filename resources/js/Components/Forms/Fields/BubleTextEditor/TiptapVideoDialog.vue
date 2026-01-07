<script setup lang="ts">
import { ref } from "vue"
import Dialog from "primevue/dialog"
import PureInput from "@/Components/Pure/PureInput.vue"
import { trans } from "laravel-vue-i18n"

const props = defineProps<{
  show: boolean
}>()

const emit = defineEmits(["close", "insert"])

const inputYoutubeUrlRef = ref<string>("")

function closeDialog() {
  emit("close")
}

function onSubmit() {
  emit("insert", inputYoutubeUrlRef.value)
  inputYoutubeUrlRef.value = ""
  closeDialog()
}
</script>

<template>
  <Dialog
    v-model:visible="props.show"
    :header="trans('Add youtube video')"
    modal
    :style="{ width: '30rem' }"
    :breakpoints="{ '960px': '90vw', '640px': '95vw' }"
    @hide="closeDialog"
  >
    <form @submit.prevent="onSubmit" class="flex flex-col space-y-5">
      <div>
        <div class="select-none text-sm text-gray-600 mb-2">Youtube Link</div>
        <PureInput
          type="url"
          id="input-add-youtube-url"
          v-model="inputYoutubeUrlRef"
          required
        />
      </div>

      <div class="flex justify-end space-x-3">
        <button
          type="button"
          class="rounded-md px-4 py-3 text-sm font-medium text-gray-600 hover:bg-gray-100"
          @click="closeDialog"
        >
          {{ trans("Cancel") }}
        </button>
        <button
          type="submit"
          class="rounded-md bg-blue-700 px-4 py-3 text-sm font-medium text-white hover:bg-blue-700/80"
        >
          {{ trans("Add") }}
        </button>
      </div>
    </form>
  </Dialog>
</template>
