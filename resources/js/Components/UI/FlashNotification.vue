<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 10 May 2025 17:23:40 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { getNotificationData } from "@/Composables/useNotificationStyles"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheckCircle } from "@fas"
import { faExclamationTriangle, faClock } from "@fad"
import { FlashNotification as FlashNotificationType } from "@/types/FlashNotification"

library.add(faCheckCircle, faClock, faExclamationTriangle)

defineProps<{
    notification?: FlashNotificationType
}>()

</script>

<template>
    <Transition name="slide-to-right">
        <div v-if="notification" class="p-4">
            <div class="px-4 py-3 rounded-md flex items-center" :class="getNotificationData(notification).class">
                <div class="text-3xl">
                    <FontAwesomeIcon :icon="getNotificationData(notification).icon" class="" fixed-width
                        aria-hidden="true" />
                </div>
                
                <div class="ml-3">
                    <h3 class="text-sm font-bold">{{ getNotificationData(notification).title }}</h3>
                    <div class="text-xs opacity-90 ">
                        <p>{{ getNotificationData(notification).description }}</p>
                    </div>
                </div>
            </div>
        </div>
    </Transition>
</template>