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
    <FontAwesomeIcon v-tooltip="information" icon='fal fa-info-circle' class="opacity-60 hover:opacity-100 cursor-pointer" fixed-width aria-hidden='true' />
</template>