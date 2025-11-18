<script setup lang="ts">
import { ref, inject } from 'vue'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faAlignLeft, faSearch } from "@fas"

const props = withDefaults(defineProps<{
    data: any
    isMaster?: boolean
}>(), {
    isMaster: false,
})


const current = ref(0)
</script>

<template>
  <div>seo sugestion</div>
</template>


<style lang="scss">
</style>
