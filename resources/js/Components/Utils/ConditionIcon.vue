<script setup lang="ts">
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faTimes } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import LoadingIcon from "./LoadingIcon.vue"
library.add(faCheck, faTimes)

const props = defineProps<{
    state?: string | null
}>()
    
</script>

<template>
    <span>
        <LoadingIcon v-if="state === 'loading'"/>
        <FontAwesomeIcon v-if="state === 'success'" icon="fal fa-check" class="text-green-500" fixed-width aria-hidden="true" />
        <FontAwesomeIcon v-if="state === 'error'" icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
    </span>
</template>