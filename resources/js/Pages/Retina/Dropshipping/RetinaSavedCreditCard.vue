<script setup lang="ts">
const props = defineProps<{
    data: { }
}>()
</script>

<template>
    <div class="p-8 text-xl font-bold w-full text-center">
        Hello, world!
        <pre>{{ data }}</pre>
    </div>
</template>