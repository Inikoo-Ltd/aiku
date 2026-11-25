<script setup lang="ts">
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faSlack } from "@fortawesome/free-brands-svg-icons"
import { faArrowRight } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import DeskIcon from "@/Components/Messaging/DeskIcon.vue"

library.add(faSlack, faArrowRight)
</script>

<template>
    <span class="inline-flex items-center gap-x-1.5" v-tooltip="ctrans('From Slack to Verba')">
        <FontAwesomeIcon icon="fab fa-slack" class="text-gray-500" fixed-width aria-hidden="true" />
        <FontAwesomeIcon icon="fal fa-arrow-right" class="text-[0.7em] text-gray-400" fixed-width aria-hidden="true" />
        <DeskIcon />
    </span>
</template>
