<template>
    <div>
        <button class="text-red-500" type="button" @click="remove(components.indexOf(file))" title="Remove file">
            <FontAwesomeIcon :icon="['fas', 'trash-alt']" class="text-xs sm:text-sm" fixed-width />
            Delete
        </button>
    </div>
</template>

<script setup lang="ts">
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faTrashAlt } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faTrashAlt)
</script>

<style scoped></style>
