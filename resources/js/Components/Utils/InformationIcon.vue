<script setup lang='ts'>
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faInfoCircle } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faInfoCircle)

const props = defineProps<{
    information: string
}>()
</script>

<template>
    <span v-tooltip="information" class="opacity-60 hover:opacity-100 cursor-pointer">
        <FontAwesomeIcon icon='fal fa-info-circle' fixed-width aria-hidden='true' />
    </span>
</template>