<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 18 Feb 2024 06:55:17 Central Standard Time, Mexico City, Mexico
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { useLocaleStore } from "@/Stores/locale"
import { useLayoutStore } from "@/Stores/retinaLayout"
import { onMounted } from 'vue'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faTimes, faPencil} from '@fal'
import { faChevronRight } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
import { useTruncate } from '@/Composables/useTruncate'
import { Link } from '@inertiajs/vue3'
import { useIsFutureIsAPast } from '@/Composables/useFormatTime'
import { trans } from 'laravel-vue-i18n'
library.add(faTimes, faPencil, faChevronRight)

const layout = useLayoutStore()

onMounted(() => {
    if (typeof window !== "undefined") {
        if (localStorage.getItem('rightSidebar')) {
            // Read from local storage then store to Pinia
            layout.rightSidebar = JSON.parse(localStorage.getItem('rightSidebar') || '')
        }
    }
})

// Remove the active bar on Right Sidebar
const onClickRemoveBar = (tabName: 'activeUsers') => {
    layout.rightSidebar[tabName].show = false
    if (typeof window !== "undefined") {
        localStorage.setItem('rightSidebar', JSON.stringify(layout.rightSidebar))
    }
}

</script>

<template>
    <div class="text-xs h-[91%] border-l border-gray-200 space-y-4 bg-white rounded-md shadow-sm">


        <!-- Add new here -->
    </div>
</template>
