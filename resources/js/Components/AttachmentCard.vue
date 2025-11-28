<script setup lang="ts">
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import {
  faLock,
  faGlobe,
  faFile,
  faCheckCircle,
  faFileCheck,
  faFilePdf,
  faFileWord,
} from "@fal"

library.add(faLock, faGlobe, faFile, faCheckCircle, faFileCheck, faFilePdf, faFileWord)

interface FileAttachment {
  id?: number
  name?: string
  type?: string
  url?: string
  size?: number
}

interface AttachmentItem {
  label: string
  scope: string
  attachment?: FileAttachment | null
}

const props = defineProps<{
  public?: AttachmentItem[]
  private?: AttachmentItem[]
}>()

// Choose icon based on file type
const getIcon = (type?: string) => {
  if (!type) return faFileCheck
  switch (type.toLowerCase()) {
    case "pdf":
      return faFilePdf
    case "doc":
    case "docx":
      return faFileWord
    default:
      return faFileCheck
  }
}
</script>

<template>
  <div class="flex flex-col gap-6 py-3">
    <!-- Public Attachments -->
  <!--   <div class="bg-white  shadow-sm border border-gray-200 overflow-hidden">
      <div class="flex items-center gap-2 px-4 py-3 border-b border-gray-100 bg-gray-50">
        <FontAwesomeIcon :icon="faGlobe" class="text-blue-500 text-base" />
        <h3 class="text-sm font-semibold text-gray-700 uppercase">Public Attachments</h3>
      </div>

      <ul v-if="props.public.filter(i => i.attachment).length" class="divide-y divide-gray-100">
        <li
          v-for="(item, index) in props.public.filter(i => i.attachment)"
          :key="'public-' + index"
          class="flex items-center justify-between px-4 py-3 text-sm hover:bg-blue-50 transition"
        >
          <div class="flex items-center gap-2">
            <FontAwesomeIcon
              :icon="getIcon(item.attachment?.type)"
              :class="[
                item.attachment ? 'text-green-500' : 'text-gray-400',
                'transition'
              ]"
            />
            <span class="text-gray-700">{{ item.label }}</span>
          </div>

          <div class="flex items-center gap-1">
            <template v-if="item.attachment">
              <a
                :href="route(item?.download_route?.name, item?.download_route?.parameters)"
                target="_blank"
                class="text-xs text-green-600 flex items-center gap-1 hover:underline"
              >
                <FontAwesomeIcon :icon="faCheckCircle" class="text-green-500 text-xs" />
                {{ item.attachment.name }}
              </a>
            </template>
            <span v-else class="text-xs italic text-gray-400">Not Uploaded</span>
          </div>
        </li>
      </ul>

      <p v-else class="text-xs text-gray-400 italic px-4 py-3">No public attachments available</p>
    </div> -->

    <!-- Private Attachments -->
    <div class="bg-white  shadow-sm border border-gray-200 overflow-hidden">
      <div class="flex items-center gap-2 px-4 py-3 border-b border-gray-100 bg-gray-50">
        <FontAwesomeIcon :icon="faLock" class="text-red-500 text-base" />
        <h3 class="text-sm font-semibold text-gray-700 uppercase">Private Attachments</h3>
      </div>

      <ul v-if="props.private.filter(i => i.attachment).length" class="divide-y divide-gray-100">
        <li
          v-for="(item, index) in props.private.filter(i => i.attachment)"
          :key="'private-' + index"
          class="flex items-center justify-between px-4 py-3 text-sm hover:bg-red-50 transition"
        >
          <div class="flex items-center gap-2">
            <!-- Dynamic icon color -->
            <FontAwesomeIcon
              :icon="getIcon(item.attachment?.type)"
              :class="[
                item.attachment ? 'text-green-500' : 'text-gray-400',
                'transition'
              ]"
            />
            <span class="text-gray-700">{{ item.label }}</span>
          </div>

          <div class="flex items-center gap-1">
            <template v-if="item.attachment">
              <a
                :href="route(item?.download_route?.name, item?.download_route?.parameters)"
                target="_blank"
                class="text-xs text-green-600 flex items-center gap-1 hover:underline"
              >
                <FontAwesomeIcon :icon="faCheckCircle" class="text-green-500 text-xs" />
                {{ item.attachment.name }}
              </a>
            </template>
            <span v-else class="text-xs italic text-gray-400">Not Uploaded</span>
          </div>
        </li>
      </ul>

      <p v-else class="text-xs text-gray-400 italic px-4 py-3">No private attachments available</p>
    </div>
  </div>
</template>
