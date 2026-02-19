<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 04 Sep 2023 11:19:39 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { trans } from 'laravel-vue-i18n'
import { useLayoutStore } from "@/Stores/layout"
import { faBriefcase } from '@fal'
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faMessage } from '@fortawesome/free-solid-svg-icons'
library.add(faBriefcase)
import { totalUnread, resetUnread } from "@/Composables/useNotificationSound"

const layout = useLayoutStore()

const onPinTab = () => {
  const sidebar = layout?.rightSidebar?.message

  if (!sidebar) return

  sidebar.show = !sidebar.show
  resetUnread()
  if (typeof window !== "undefined") {
    localStorage.setItem(
      "rightSidebar",
      JSON.stringify(layout.rightSidebar)
    )
  }
}



</script>

<template>
  <div class="group inline-flex items-center px-3 h-full font-medium hover:bg-gray-800 text-gray-200" @click="onPinTab">
    <div class="relative flex items-center gap-2 text-xs">

      <!-- Icon -->
      <div class="relative flex items-center justify-center w-4 h-4">
        <FontAwesomeIcon :icon="faMessage" class="text-[12px]" />

        <span v-if="totalUnread > 0" class="absolute -top-5 left-1/2 -translate-x-1/2
                 px-2 py-[2px]
                 bg-red-500 text-white text-[9px] font-semibold
                 rounded-full whitespace-nowrap
                 animate-pulse">
          New Messages ({{ totalUnread }})
        </span>
      </div>

      <!-- Label -->
      <span>
        {{ trans('Message') }}
      </span>

    </div>
  </div>
</template>
