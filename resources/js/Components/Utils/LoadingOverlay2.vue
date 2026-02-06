<script setup lang="ts">
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faSpinnerThird } from '@fad'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faSpinnerThird)
</script>

<template>
    <div class="absolute w-full h-full bg-black/60 flex items-center justify-center text-4xl text-white">
        <FontAwesomeIcon icon='fad fa-spinner-third' class='animate-spin' fixed-width aria-hidden='true' />
    </div>
</template>