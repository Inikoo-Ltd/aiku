<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 24 Nov 2026 14:40:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faExclamationTriangle } from "@fortawesome/free-solid-svg-icons"
import { ctrans } from "@/Composables/useTrans"
import { chatWriterOn } from "@/Composables/useChatDrafting"

/** HELP-4896: over the reply box while a colleague is writing a reply to the same customer. */
const props = defineProps<{
    sessionUlid: string | null | undefined
    isWritingToo: boolean
}>()

const writer = computed(() => chatWriterOn(props.sessionUlid))
</script>

<template>
    <div v-if="writer" role="alert"
        class="mx-3 mb-1 flex items-center gap-2 rounded-lg border-2 border-red-500 bg-red-50 px-3 py-2 text-sm font-semibold text-red-700">
        <FontAwesomeIcon :icon="faExclamationTriangle" class="shrink-0" fixed-width aria-hidden="true" />
        {{ isWritingToo
            ? ctrans(":name is also writing a reply to this customer. Check with them before you send yours.", { name: writer.name })
            : ctrans(":name is writing a reply to this customer. Leave this one to them.", { name: writer.name }) }}
    </div>
</template>
