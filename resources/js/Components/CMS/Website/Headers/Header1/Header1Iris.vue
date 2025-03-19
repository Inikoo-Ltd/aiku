<script setup lang="ts">
import { getStyles } from "@/Composables/styles";
import { checkVisible } from "@/Composables/Workshop";
import { inject } from "vue";
import Image from "@/Components/Image.vue";

import { 
  faPresentation, 
  faCube, 
  faText, 
  faPaperclip 
} from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { 
  faChevronRight, 
  faSignOutAlt, 
  faShoppingCart, 
  faSearch, 
  faChevronDown, 
  faTimes, 
  faPlusCircle, 
  faBars, 
  faUserCircle, 
  faImage, 
  faSignInAlt, 
  faFileAlt 
} from "@fas";
import { faHeart } from "@far";

library.add(
  faPresentation, 
  faCube, 
  faText, 
  faImage, 
  faPaperclip, 
  faChevronRight, 
  faSignOutAlt, 
  faShoppingCart, 
  faHeart, 
  faSearch, 
  faChevronDown, 
  faTimes, 
  faPlusCircle, 
  faBars, 
  faUserCircle, 
  faSignInAlt, 
  faFileAlt
);

const props = defineProps<{
  fieldValue: {
    headerText: string;
    chip_text: string;
    container: {
      properties: Record<string, string>;
    };
    logo: {
      properties: Record<string, string>;
      alt: string;
      image: {
        source: string;
      };
    };
    button_1: {
      text: string;
      visible: boolean | null;
      container: {
        properties: Record<string, string>;
      };
    };
  };
  loginMode: boolean;
}>();

const isLoggedIn = inject("isPreviewLoggedIn", false);
</script>

<template>
  <div class="shadow-sm" :style="getStyles(fieldValue.container.properties)">
    <div class="flex flex-col justify-between items-center py-4 px-6">
      <div class="w-full grid grid-cols-3 items-center gap-6">
        <!-- Logo -->
        <component
					:is="fieldValue?.logo?.url ? 'a' : 'div'"
					:href="fieldValue?.logo?.url || '#'"
				>
        <!-- <Image
						:alt="fieldValue?.logo?.alt"
						:src="fieldValue?.logo?.image?.source"
						:style="getStyles(fieldValue?.logo?.properties)"
						:imgAttributes="{
							loading:'lazy'
						}"
					/> -->

          <Image 
                        :style="getStyles(fieldValue.logo.properties)"
                        :alt="fieldValue?.logo?.alt" 
                         :imageCover="true"
                        :src="fieldValue?.logo?.image?.source" 
                        :imgAttributes="fieldValue?.logo.image?.attributes"
                       >
                    </Image>
        </component>

        <!-- Search Bar -->
        <div class="relative justify-self-center w-full max-w-md">
          <!-- Search bar can be added here if needed -->
        </div>

        <!-- Gold Member Button -->
        <div class="justify-self-end w-fit">
          <div 
            v-if="checkVisible(fieldValue.button_1.visible, isLoggedIn)" 
            class="space-x-1.5 cursor-pointer whitespace-nowrap" 
            :style="getStyles(fieldValue.button_1.container.properties)"
          >
            <span v-html="fieldValue.button_1.text" />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped></style>
