<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 09 May 2025 14:26:55 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">

import { trans } from "laravel-vue-i18n";
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

onMounted(() => {
    setTimeout(() => {
        window.close()
    }, 1000)
})


</script>

<template>
  <div class="px-4">
      <h1>Close this now, your store was successfully authenticated, what are you waiting for? there are lot of steps which you need to complete.</h1>
  </div>
</template>
