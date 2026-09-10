<script setup lang="ts">
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCircle } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
library.add(faCircle)
</script>

<template>
    <span class="relative inline-flex items-center justify-center">
        <span class="absolute -xtranslate-y-1/2 xtop-1/2 ">
            <FontAwesomeIcon icon="fas fa-circle" fixed-width aria-hidden="true" class="animate-ping" />
        </span>
        <FontAwesomeIcon icon="fas fa-circle" fixed-width aria-hidden="true" />
    </span>
</template>