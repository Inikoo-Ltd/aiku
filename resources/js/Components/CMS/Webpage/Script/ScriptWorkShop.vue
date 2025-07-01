<script setup lang="ts">
import { faCube, faStar } from "@fortawesome/free-solid-svg-icons";
import { library } from "@fortawesome/fontawesome-svg-core";
import Blueprint from "./Blueprint";
import { inject } from "vue";
import { getStyles } from "@/Composables/styles"

library.add(faCube, faStar);

const props = defineProps<{
  modelValue: any
  webpageData?: any
  blockData?: Object
  screenType: "mobile" | "tablet" | "desktop"
}>()

const layout: any = inject("layout", {})
const bKeys = Blueprint?.blueprint?.map(b => b?.key?.join("-")) || []
</script>

<template>
  <div id="script">
    <div :style="getStyles(layout?.app?.webpage_layout?.container?.properties, screenType)" 
      class="w-full py-6 px-6 flex gap-x-10 editor-class overflow-x-auto font-mono">
      <div v-html="modelValue.value"></div>
    </div>
  </div>

</template>
