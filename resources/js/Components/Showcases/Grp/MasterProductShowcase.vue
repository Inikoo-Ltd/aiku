<script setup lang="ts">
import TranslationBox from '@/Components/TranslationBox.vue';


const props = defineProps<{
  data:object
}>()
console.log(props.data)
</script>

<template>
    <div class="px-4 pb-8 m-5">
        <TranslationBox 
            :master="data.data" 
            :needTranslation="data.data" 
            v-bind="data.data.translation_box" 
        />
    </div>
</template>
