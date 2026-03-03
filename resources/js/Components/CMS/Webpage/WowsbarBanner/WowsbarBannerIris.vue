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
    banner_id: number
    banner_responsive: {
      id: number,
      slug: string,
      name: string
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

const bannerRatio = computed(() => {
  return data.value?.ratio ?? '4/1'
})

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

    const components = response.data.compiled_layout.components.filter((item: any) => item?.visibility == true)

    data.value = {
      ...response.data,
      compiled_layout: {
        ...response.data.compiled_layout,
        components: components
      }
    }
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

const bannerDimensionStyle = computed(() => {
  const styles = getStyles(
    props.fieldValue?.banner_dimension?.properties,
    props.screenType,
    false
  ) || {}

  return {
    width: styles.width ?? '100%',
    height: styles.height ?? '100%'
  }
})

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

    <section v-else-if="data" class="relative mx-auto" :style="bannerDimensionStyle">
      <div :style="{
        ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
        ...getStyles(fieldValue.container?.properties, screenType)
      }">

        <div v-if="data?.compiled_layout?.type === 'landscape'" :class="[
					'mx-auto',
					bannerRatio !== '4/1' && 'max-w-full sm:max-w-2xl md:max-w-4xl lg:max-w-6xl xl:max-w-[1600px]'
				]" :style="bannerDimensionStyle">
          <SliderLandscape :data="data.compiled_layout" :production="true" :view="screenType" :ratio="bannerRatio" />
        </div>

        <SliderSquare v-else-if="data?.compiled_layout?.type === 'square'" :data="data.compiled_layout"
          :production="true" :view="screenType" :ratio="bannerRatio" />

        <SliderSquare v-else-if="data?.compiled_layout?.type === 'square'" :data="data.compiled_layout"
          :production="true" :view="screenType" :ratio="bannerRatio" />
      </div>

    </section>

  </div>
</template>