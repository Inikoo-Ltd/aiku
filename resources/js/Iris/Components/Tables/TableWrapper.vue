<script setup lang="ts">
defineProps<{
    result: boolean
}>()
</script>

<template>
    <div class="flex flex-col">
        <div class="">
            <div class="inline-block min-w-full"
                :class="[result ? 'align-middle' : 'align-top']">
                <div class="shadow border-b border-gray-200 relative">
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
