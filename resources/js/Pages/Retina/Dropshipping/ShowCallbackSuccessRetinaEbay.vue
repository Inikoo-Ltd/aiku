<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 09 May 2025 14:26:55 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">

import { ctrans } from "@/Composables/useTrans";
import { faCheckCircle } from "@fas";
import { faUnlink } from "@fal";
import { faExclamationTriangle, faClock } from "@fad";
import { library } from "@fortawesome/fontawesome-svg-core";
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue";
import FlashNotification from "@/Components/UI/FlashNotification.vue";
import {FlashNotification  as FlashNotificationType} from "@/types/FlashNotification";

import { PageProps as InertiaPageProps } from "@inertiajs/core";
import { usePage } from "@inertiajs/vue3";
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import { routeType } from "@/types/route"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { onMounted } from "vue"

library.add(faCheckCircle, faUnlink, faExclamationTriangle, faClock)

const props = defineProps<{
    success?: boolean
}>()

onMounted(() => {
    if (props.success !== false) {
        setTimeout(() => {
            window.close()
        }, 1000)
    }
})


</script>

<template>
  <div class="px-4">
      <h1 v-if="props.success !== false">{{ ctrans("Your store was successfully authenticated. You can close this window now.") }}</h1>
      <div v-else class="flex flex-col gap-2 max-w-2xl">
          <h1 class="text-lg font-semibold">{{ ctrans("Your store approved the connection but could not send us the keys") }}</h1>
          <p>{{ ctrans("WooCommerce tried to post the new API keys to our servers from your hosting and that request failed. This usually means your hosting blocks outgoing connections or has a firewall rule against our servers.") }}</p>
          <p>{{ ctrans("You can still connect: in WooCommerce go to Settings, Advanced, REST API, create a key with Read/Write permissions, and paste the consumer key and secret into the connection form in AW.") }}</p>
      </div>
  </div>
</template>
