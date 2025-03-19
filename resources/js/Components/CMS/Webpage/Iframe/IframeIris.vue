<script setup lang="ts">
import { onMounted, onUnmounted, ref, computed } from "vue";
import { faPresentation, faLink, faPaperclip } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { getStyles } from "@/Composables/styles.js";
import { v4 as uuidv4 } from "uuid";

library.add(faPresentation, faLink, faPaperclip);

const props = defineProps<{
  fieldValue: any;
  webpageData?: any;
  blockData?: Object;
}>();

const screenWidth = ref(0);

// Fungsi untuk memperbarui ukuran layar saat berubah
const updateScreenWidth = () => {
  if (window) {
    screenWidth.value = window.innerWidth;
  }
};

onMounted(() => {
  if (window) {
    window.addEventListener("resize", updateScreenWidth);
  }
});

onUnmounted(() => {
  if (window) {
    window.removeEventListener("resize", updateScreenWidth);
  }
});

// Menentukan ukuran berdasarkan kondisi Mobile/Desktop
const iframeStyles = computed(() => {
  const baseStyles = getStyles(props.fieldValue?.container?.properties) || {};

  if (props.fieldValue?.link?.includes("wowsbar")) {
    if (screenWidth.value <= 768) {
      // Mobile (≤768px)
      return {
        ...baseStyles,
        width: "100%",
        height: "26vh", // Bisa diatur sesuai kebutuhan
      };
    } 
  }

  return baseStyles;
});

const isMounted = ref(false)
onMounted(() => {
  isMounted.value = true
})
</script>

<template>
  <iframe 
    v-if="isMounted"
    :title="fieldValue?.title || `iframe-${uuidv4()}`"
    :src="fieldValue?.link" 
    :style="iframeStyles"
    loading="lazy" 
    referrerpolicy="no-referrer-when-downgrade"
  />

  <!-- Loading (skeleton) -->
  <div v-else :style="iframeStyles" class="skeleton">
  </div>
</template>
