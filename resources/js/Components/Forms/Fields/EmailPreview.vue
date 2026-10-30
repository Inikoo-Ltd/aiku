<script setup lang="ts">
defineOptions({ inheritAttrs: false })

defineProps<{
    fieldData: {
        value: string
    }
}>()
</script>

<template>
    <!-- Rendered by the code that sends the email, on an example order: never customer input -->
    <div class="rounded border border-gray-200 bg-gray-50 px-5 pt-4" v-html="fieldData.value" />
</template>
