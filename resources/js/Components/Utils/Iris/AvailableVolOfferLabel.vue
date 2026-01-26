<script setup lang="ts">

defineProps<{
    offer: {
        products_triggers_label: string
    }
}>()

</script>

<template>
    <div class="bg-gray-50 border border-orange-500 border-b-4 rounded-md px-2 py-1 leading-3 text-xxs">
        <span>{{ offer?.products_triggers_label ?? "-" }}</span>
    </div>
</template>