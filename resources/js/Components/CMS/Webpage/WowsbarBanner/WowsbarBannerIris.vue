<script setup lang="ts">
import { getStyles } from "@/Composables/styles";
import { ref, onMounted, inject, watch, computed, defineAsyncComponent } from "vue";
import axios from "axios";
import { notify } from "@kyvg/vue3-notification";
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue";

const SliderSquare = defineAsyncComponent(() => import("@/Components/Banners/Slider/SliderSquare.vue"));
const SliderLandscape = defineAsyncComponent(() => import("@/Components/Banners/Slider/SliderLandscape.vue"));

const props = defineProps<{
    fieldValue: {
        id?: string
        compiled_layout?: any
        banner_id : number
        banner_responsive : {
            id : number,
            slug : string,
            name : string
        }
        container: {
            properties: any
        }
    }
    screenType: "mobile" | "tablet" | "desktop"
}>();

const layout = inject("layout");
const data = ref<any>(null);
const isLoading = ref(false);

const activeId = computed(() => {
	const responsive = props.fieldValue?.banner_responsive;

	if (!responsive) {
		return props.fieldValue?.banner_id ?? null;
	}

	const current = responsive?.[props.screenType]?.id;

	if (!current && props.screenType !== "desktop") {
		return responsive?.desktop?.id ?? null;
	}

	return current ?? null;
});

const getDataBanner = async (): Promise<void> => {
  if (typeof window === "undefined") return;

  if (!activeId.value) {
    data.value = null;
    return;
  }

  try {
    isLoading.value = true;

    const response = await axios.get(
      `/json/banner/${activeId.value}`
    );

    data.value = response.data;
  } catch (error: any) {
    notify({
      title: "Failed to fetch banners data",
      text: error?.message || "An error occurred",
      type: "error",
    });
    data.value = null;
  } finally {
    isLoading.value = false;
  }
};


watch(
	activeId,
	(newId, oldId) => {
		if (newId !== oldId) {
			getDataBanner();
		}
	},
	{ immediate: true }
);

onMounted(() => {
	if (activeId.value) {
		getDataBanner();
	}
});
</script>

<template>
    <div :id="fieldValue?.id ? fieldValue?.id : 'banner'" component="banner">
        <div v-if="isLoading" class="flex justify-center h-36 items-center">
			<LoadingIcon class="text-4xl" />
		</div>
        <div
            :style="{
                ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
                ...getStyles(fieldValue.container?.properties, screenType)
            }"
        >
            <SliderLandscape
                v-if="data?.compiled_layout?.type === 'landscape'"
                :data="data.compiled_layout"
                :production="true"
                :view="screenType"
                :ratio="data.ratio"
            />

            <SliderSquare
                v-else-if="data?.compiled_layout?.type === 'square'"
                :data="data.compiled_layout"
                :production="true"
                :view="screenType"
                :ratio="data.ratio"
            />
        </div>
    </div>
</template>