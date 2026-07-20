<script setup>
import { watch, ref } from 'vue';

const props = defineProps({
    isLoading: {
        type: Boolean,
        default: false
    },
    position: {
        type: String,
        default: 'fixed'
    }
});

const show = ref(false);
let timer = null;

watch(() => props.isLoading, (isLoading) => {
    clearTimeout(timer);
    if (isLoading) {
        show.value = true;
    } else {
        timer = setTimeout(() => {
            show.value = false;
        }, 500);
    }
})
</script>
<template>
  <div v-if="show"
    :class="position === 'absolute' ? 'absolute' : 'fixed'"
    class="animate-fade-in inset-0 bg-black/30 flex items-center justify-center z-[9999]">
    <div class="w-12 h-12 border-4 border-white border-t-transparent rounded-full animate-spin">
    </div>
  </div>
</template>
<style lang="scss">
    @keyframes fade-in {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    .animate-fade-in {
        animation: fade-in 0.2s ease-in;
    }
</style>
