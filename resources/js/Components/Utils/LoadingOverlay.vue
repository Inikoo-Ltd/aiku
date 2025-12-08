<script setup>
import { watch, ref } from 'vue';

const props = defineProps({
    isLoading: {
        type: Boolean,
        default: false
    }
});

const show = ref(false);
let timer = null;

watch(props, (val) => {
    clearTimeout(timer);
    if (val.isLoading) {
        show.value = true;
    } else {
        timer = setTimeout(() => {
            show.value = true;
        }, 500);
    }
})
</script>
<template>  
  <div v-if="show" class="animate-fade-in fixed inset-0 bg-black/30  flex items-center justify-center z-[9999]">
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