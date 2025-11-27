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

const layout = useLayoutStore()

const onPinTab = () => {
  const sidebar = layout?.rightSidebar?.message

  if (!sidebar) return

  sidebar.show = !sidebar.show

  if (typeof window !== "undefined") {
    localStorage.setItem(
      "rightSidebar",
      JSON.stringify(layout.rightSidebar)
    )
  }
}


console.log('message',layout)
</script>

<template>
    <div class="group inline-flex items-center px-3 h-full font-medium hover:bg-gray-800 text-gray-200" @click="onPinTab">
        <div class="flex items-center gap-2 text-xs">
            <div class="flex items-center justify-center w-4 h-4">
                <FontAwesomeIcon :icon="faMessage" class="text-[10px]" />
            </div>
            <span>{{ trans('Message') }}</span>
        </div>
    </div>
</template>
