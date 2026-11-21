<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 21 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheck, faCheckDouble } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import type { TickState } from "@/Stores/staff-messaging"

library.add(faCheck, faCheckDouble)

const props = defineProps<{
    state: TickState
}>()

const label = computed(() => ({ sent: ctrans("Sent"), received: ctrans("Received"), read: ctrans("Read") })[props.state])
</script>

<template>
    <FontAwesomeIcon
        :icon="state === 'sent' ? 'fal fa-check' : 'fal fa-check-double'"
        :class="state === 'read' ? 'text-sky-500' : 'text-gray-400'"
        fixed-width
        :aria-label="label" />
</template>
