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
  indexBlock : number
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

const MOBILE_BANNER_HEIGHT = '300px'
const MOBILE_BANNER_WIDTH = '375px'
const SQUARE_BANNER_HEIGHT = '400px'
const LANDSCAPE_RATIO_THRESHOLD = 1.5

const mobileImageRatio = ref<number | null>(null)

const bannerRatio = computed(() => {
  return data.value?.ratio ?? '4/1'
})

const bannerType = computed(() => {
  return data.value?.compiled_layout?.type
    ?? props.fieldValue?.compiled_layout?.type
    ?? 'landscape'
})

// determine the banner box style
const reservedBannerStyle = computed(() => {
  if (bannerType.value === 'square') {
    return props.screenType === 'mobile'
      ? { width: '100%', aspectRatio: '1 / 1' }
      : { width: '100%', height: SQUARE_BANNER_HEIGHT }
  }

  if (props.screenType === 'mobile') {
    const ratio = mobileImageRatio.value

    if (ratio && ratio < LANDSCAPE_RATIO_THRESHOLD) {
      return { width: MOBILE_BANNER_WIDTH, maxWidth: '100%', aspectRatio: `${ratio}` }
    }

    return { width: '100%', height: MOBILE_BANNER_HEIGHT }
  }

  const [w, h] = (bannerRatio.value || '4/1').split('/').map(Number)

  return {
    width: '100%',
    aspectRatio: w > 0 && h > 0 ? `${w} / ${h}` : '4 / 1',
  }
})

const firstBannerImageSource = computed<string | null>(() => {
  const image = data.value?.compiled_layout?.components?.[0]?.image
  if (!image) {
    return null
  }

  const pickSource = (variant: any): string | null =>
    variant?.thumbnail?.webp
    ?? variant?.thumbnail?.original
    ?? variant?.source?.webp
    ?? variant?.source?.original
    ?? null

  return pickSource(image[props.screenType]) ?? pickSource(image.desktop)
})

// measure the original ratio of the image
const measureImageRatio = (source: string | null): void => {
  mobileImageRatio.value = null

  if (typeof window === "undefined" || !source) {
    return
  }

  const image = new window.Image()
  image.onload = () => {
    if (image.naturalWidth > 0 && image.naturalHeight > 0) {
      mobileImageRatio.value = image.naturalWidth / image.naturalHeight
    }
  }
  image.src = source
}

watch(firstBannerImageSource, (source) => measureImageRatio(source), { immediate: true })

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

onMounted(() => {
  if (activeId.value) {
    getDataBanner();
  }
});
</script>

<template>  
  <div :id="fieldValue?.id ? fieldValue?.id : 'banner'+indexBlock" component="banner">
    <div v-if="isLoading" class="flex justify-center items-center mx-auto" :style="reservedBannerStyle">
      <LoadingIcon class="text-4xl" />
    </div>

    <section v-else-if="data" class="relative mx-auto" :style="reservedBannerStyle">
      <div class="w-full h-full" :style="{
        ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
        ...getStyles(fieldValue.container?.properties, screenType),
      }">

        <div v-if="data?.compiled_layout?.type === 'landscape'" class="mx-auto w-full h-full"
          :class="bannerRatio !== '4/1' && 'max-w-full sm:max-w-2xl md:max-w-4xl lg:max-w-6xl xl:max-w-[1600px]'">
          <SliderLandscape :data="data.compiled_layout" :production="true" :view="screenType" :ratio="bannerRatio" />
        </div>

        <SliderSquare v-else-if="data?.compiled_layout?.type === 'square'" :data="data.compiled_layout"
          :production="true" :view="screenType" :ratio="bannerRatio" />
      </div>

    </section>

  </div>
</template>