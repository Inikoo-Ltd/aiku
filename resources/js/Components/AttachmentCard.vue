<script setup lang="ts">
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faLock, faGlobe, faFile, faCheckCircle } from "@fal"

library.add(faLock, faGlobe, faFile, faCheckCircle)

interface AttachmentItem {
  label: string
  scope: string
  id?: number | null
  file?: string | null
  size?: number | null
}

const props = defineProps<{
  public?: AttachmentItem[]
  private?: AttachmentItem[]
}>()
</script>

<template>
  <div class="flex flex-col gap-6 p-4">
    <!-- Public Attachments -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
      <div class="flex items-center gap-2 px-4 py-3 border-b border-gray-100 bg-gray-50">
        <FontAwesomeIcon :icon="faGlobe" class="text-blue-500 text-base" />
        <h3 class="text-sm font-semibold text-gray-700 uppercase">Public Attachments</h3>
      </div>

      <ul v-if="props.public?.length" class="divide-y divide-gray-100">
        <li
          v-for="(item, index) in props.public"
          :key="'public-' + index"
          class="flex items-center justify-between px-4 py-3 text-sm hover:bg-blue-50 transition"
        >
          <div class="flex items-center gap-2 text-gray-700">
            <FontAwesomeIcon :icon="faFile" class="text-gray-400" />
            <span>{{ item.label }}</span>
          </div>

          <div class="flex items-center gap-1">
            <span
              v-if="item.id"
              class="text-xs text-green-600 flex items-center gap-1"
            >
              <FontAwesomeIcon :icon="faCheckCircle" class="text-green-500 text-xs" />
              Uploaded
            </span>
            <span
              v-else
              class="text-xs italic text-gray-400"
            >
              Not Uploaded
            </span>
          </div>
        </li>
      </ul>

      <p v-else class="text-xs text-gray-400 italic px-4 py-3">No public attachments available</p>
    </div>

    <!-- Private Attachments -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
      <div class="flex items-center gap-2 px-4 py-3 border-b border-gray-100 bg-gray-50">
        <FontAwesomeIcon :icon="faLock" class="text-red-500 text-base" />
        <h3 class="text-sm font-semibold text-gray-700 uppercase">Private Attachments</h3>
      </div>

      <ul v-if="props.private?.length" class="divide-y divide-gray-100">
        <li
          v-for="(item, index) in props.private"
          :key="'private-' + index"
          class="flex items-center justify-between px-4 py-3 text-sm hover:bg-red-50 transition"
        >
          <div class="flex items-center gap-2 text-gray-700">
            <FontAwesomeIcon :icon="faFile" class="text-gray-400" />
            <span>{{ item.label }}</span>
          </div>

          <div class="flex items-center gap-1">
            <span
              v-if="item.id"
              class="text-xs text-green-600 flex items-center gap-1"
            >
              <FontAwesomeIcon :icon="faCheckCircle" class="text-green-500 text-xs" />
              {{ item.attachment.name }}
            </span>
            <span
              v-else
              class="text-xs italic text-gray-400"
            >
              Not Uploaded
            </span>
          </div>
        </li>
      </ul>

      <p v-else class="text-xs text-gray-400 italic px-4 py-3">No private attachments available</p>
    </div>
  </div>
</template>
