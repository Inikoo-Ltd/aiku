<script setup lang="ts">
import { computed, ref, watch } from "vue"
import axios from "axios"
import Dialog from "primevue/dialog"
import ProgressBar from "primevue/progressbar"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { ScriptBase64File } from "@/Composables/Workshop"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faExclamationTriangle, faCheckCircle, faTimesCircle } from "@fas"
import { faFilePdf, faFile } from "@fal"

type UploadStatus = "pending" | "uploading" | "done" | "failed"

const props = defineProps<{
  webpageId: number
  files: ScriptBase64File[]
}>()

const emits = defineEmits<{
  (e: "repaired"): void
  (e: "checkPage"): void
  (e: "publish"): void
}>()

const visible = defineModel<boolean>("visible", { default: false })

const phase = ref<"review" | "uploading" | "done">("review")
const uploads = ref<Record<string, { status: UploadStatus, progress: number, error?: string }>>({})

watch(visible, (isVisible) => {
  if (!isVisible) return
  phase.value = "review"
  uploads.value = Object.fromEntries(props.files.map(file => [file.key, { status: "pending", progress: 0 }]))
})

const finishedCount = computed(() =>
  Object.values(uploads.value).filter(upload => upload.status === "done" || upload.status === "failed").length
)
const uploadedCount = computed(() => Object.values(uploads.value).filter(upload => upload.status === "done").length)
const failedCount = computed(() => Object.values(uploads.value).filter(upload => upload.status === "failed").length)
const overallProgress = computed(() => props.files.length ? Math.round(finishedCount.value * 100 / props.files.length) : 0)

const isImage = (file: ScriptBase64File) => file.mimeType.startsWith("image/")

const fileTypeLabel = (file: ScriptBase64File) =>
  file.mimeType === "application/pdf" ? "PDF" : file.mimeType.replace(/^image\//, "").toUpperCase()

const formatSize = (bytes: number) => {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / 1024 / 1024).toFixed(1)} MB`
}

const uploadErrorMessage = (error: any): string => {
  const response = error?.response

  if (!response) return ctrans("Network error, check your connection and try again.")

  switch (response.status) {
    case 413:
      return ctrans("The file is too large to upload. Make it smaller, or upload it to the website and link to it in the script.")
    case 419:
      return ctrans("Your session has expired. Reload the page and try again.")
    case 403:
      return ctrans("You do not have permission to edit this webpage.")
    case 404:
      return ctrans("This script block is no longer on the webpage. Reload the page.")
    case 422:
      return response.data?.errors?.data_uri?.[0] || response.data?.message || ctrans("This file cannot be uploaded.")
    default:
      return ctrans("The server could not upload the file (error :status). Try again later.", { status: response.status })
  }
}

const uploadFile = async (file: ScriptBase64File): Promise<void> => {
  const upload = uploads.value[file.key]
  upload.status = "uploading"
  upload.progress = 0
  upload.error = undefined

  try {
    await axios.post(
      route("grp.models.webpage.web_block.repair_base64_file", {
        webpage: props.webpageId,
        modelHasWebBlock: file.modelHasWebBlockId,
      }),
      { data_uri: file.dataUri },
      {
        onUploadProgress: (event) => {
          upload.progress = event.total ? Math.round(event.loaded * 100 / event.total) : 0
        },
      }
    )
    upload.progress = 100
    upload.status = "done"
  } catch (error: any) {
    upload.status = "failed"
    upload.error = uploadErrorMessage(error)
  }
}

const uploadFiles = async (files: ScriptBase64File[]) => {
  phase.value = "uploading"

  for (const file of files) {
    await uploadFile(file)
  }

  phase.value = "done"
  emits("repaired")
}

const retryFailedFiles = () => uploadFiles(props.files.filter(file => uploads.value[file.key]?.status === "failed"))
</script>

<template>
  <Dialog v-model:visible="visible" modal :closable="phase !== 'uploading'" :header="ctrans('Embedded files found')"
    :style="{ width: '44rem' }" :breakpoints="{ '768px': '95vw' }">
    <div class="space-y-4 text-sm text-slate-700">
      <div v-if="phase === 'review'" class="flex gap-3">
        <FontAwesomeIcon :icon="faExclamationTriangle" class="mt-0.5 text-orange-500" fixed-width />
        <p>
          {{ ctrans('This webpage has :count base64 files embedded in its script blocks. They need to be uploaded to the website before publishing.', { count: files.length }) }}
        </p>
      </div>

      <div v-else class="space-y-1.5">
        <div class="flex justify-between text-xs text-slate-500">
          <span>{{ ctrans(':done of :total files processed', { done: finishedCount, total: files.length }) }}</span>
          <span>{{ overallProgress }}%</span>
        </div>
        <ProgressBar :value="overallProgress" :showValue="false" style="height: 6px" />
      </div>

      <ul class="max-h-[50vh] divide-y divide-slate-100 overflow-y-auto rounded border border-slate-200">
        <li v-for="file in files" :key="file.key" class="flex items-center gap-3 px-3 py-2">
          <div class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded border border-slate-200 bg-slate-50">
            <img v-if="isImage(file)" :src="file.dataUri" alt="" class="h-full w-full object-contain" />
            <FontAwesomeIcon v-else :icon="file.mimeType === 'application/pdf' ? faFilePdf : faFile" class="text-slate-400" size="lg" />
          </div>

          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
              <span class="font-medium">{{ fileTypeLabel(file) }}</span>
              <span class="text-xs text-slate-400">{{ formatSize(file.size) }}</span>
            </div>
            <div class="truncate text-xs text-slate-500">{{ file.blockLabel }}</div>

            <div v-if="uploads[file.key]?.status === 'uploading'" class="mt-1">
              <ProgressBar :value="uploads[file.key].progress" :showValue="false" style="height: 4px" />
              <span v-if="uploads[file.key].progress === 100" class="text-[11px] text-slate-400">{{ ctrans('Processing...') }}</span>
            </div>
            <div v-else-if="uploads[file.key]?.status === 'failed'" class="mt-0.5 text-xs text-red-600">
              {{ uploads[file.key].error }}
            </div>
          </div>

          <FontAwesomeIcon v-if="uploads[file.key]?.status === 'done'" :icon="faCheckCircle" class="text-green-500" fixed-width />
          <FontAwesomeIcon v-else-if="uploads[file.key]?.status === 'failed'" :icon="faTimesCircle" class="text-red-500" fixed-width />
        </li>
      </ul>

      <div v-if="phase === 'done'" class="space-y-1">
        <p>{{ ctrans(':count files were uploaded.', { count: uploadedCount }) }}</p>
        <p v-if="failedCount" class="text-orange-600">
          {{ ctrans(':count files could not be uploaded and were left as they are.', { count: failedCount }) }}
        </p>
        <p class="text-slate-500">
          {{ failedCount
            ? ctrans('Check and edit the failed files in the script blocks, or publish now.')
            : ctrans('Check that the images and files show correctly before publishing, or publish now.') }}
        </p>
      </div>
    </div>

    <template #footer>
      <div v-if="phase !== 'done'" class="flex items-center justify-end gap-2">
        <Button type="tertiary" size="xs" :label="ctrans('Cancel')" :disabled="phase === 'uploading'"
          @click="visible = false" />
        <Button type="save" size="xs" :label="ctrans('Upload all')" :loading="phase === 'uploading'" @click="uploadFiles(files)" />
      </div>
      <div v-else class="flex items-center justify-end gap-2">
        <Button v-if="failedCount" type="tertiary" size="xs" :label="ctrans('Retry failed')" @click="retryFailedFiles" />
        <Button type="tertiary" size="xs" :label="ctrans('Check and edit')" @click="emits('checkPage')" />
        <Button type="save" size="xs" icon="far fa-rocket-launch" :label="ctrans('Publish now')"
          @click="emits('publish')" />
      </div>
    </template>
  </Dialog>
</template>
