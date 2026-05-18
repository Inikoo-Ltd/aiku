<script setup>
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faTimes } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faTimes)

const emit = defineEmits(['resetSearch'])

</script>

<template>
  <button
    ref="button"
    type="button"
    dusk="reset-table"
    class="w-full rounded-md shadow-sm px-4 py-2 inline-flex justify-center text-sm font-medium border border-red-200 text-red-400 hover:text-red-500 hover:bg-red-100/50 "
    aria-haspopup="true"
    @click="emit('resetSearch', true)"
  >
    <FontAwesomeIcon icon="fal fa-times"  class="h-5 w-5 mr-2" aria-hidden="true" />
    <span class="">Reset</span>
  </button>
</template>


