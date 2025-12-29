<script setup lang="ts">
const props = defineProps<{
    data: {}
}>()
</script>

<template>
    <div>
        <pre>{{ data }}</pre>
    </div>
</template>