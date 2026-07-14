<script setup lang="ts">
import { getStyles } from "@/Composables/styles";
import { ref, onMounted, inject, watch, computed, defineAsyncComponent } from "vue";
import axios from "axios";
import { notify } from "@kyvg/vue3-notification";

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

const detectScreenType = (): "mobile" | "tablet" | "desktop" => {
  if (typeof window === "undefined") return props.screenType ?? "desktop";
  if (window.innerWidth < 640) return "mobile";
  if (window.innerWidth < 1024) return "tablet";
  return "desktop";
};

const effectiveScreenType = ref<"mobile" | "tablet" | "desktop">(detectScreenType());

watch(
  () => props.screenType,
  () => {
    effectiveScreenType.value = detectScreenType();
  }
);

const activeId = computed(() => {
  const responsive = props.fieldValue?.banner_responsive;

  if (!responsive) {
    return props.fieldValue?.banner_id ?? null;
  }

  const current = responsive?.[effectiveScreenType.value]?.id;

  if (!current && effectiveScreenType.value !== "desktop") {
    return responsive?.desktop?.id ?? null;
  }

  return current ?? null;
});

const embeddedBannerData = (id: number | string | null) => {
  const banner = id != null ? props.fieldValue?.banners_data?.[id] : null;

  return banner?.compiled_layout ? banner : null;
};

data.value = embeddedBannerData(activeId.value);

const MOBILE_BANNER_HEIGHT = '300px'
const MOBILE_BANNER_WIDTH = '375px'
const SQUARE_BANNER_HEIGHT = '400px'
const LANDSCAPE_RATIO_THRESHOLD = 1.5

const bannerRatio = computed(() => {
  return data.value?.ratio ?? '4/1'
})

/*
 * Resolve the embedded banner data for a given view directly from fieldValue,
 * independent of the active banner. On the server activeId settles on desktop
 * (no window), so deriving the mobile box from the active banner recomputes it
 * on the client and shifts the layout. Reading each view's own banner keeps the
 * reserved geometry stable from first paint on every device.
 */
const resolveBannerId = (view: 'mobile' | 'desktop'): number | string | null => {
  const responsive = props.fieldValue?.banner_responsive

  if (!responsive) {
    return props.fieldValue?.banner_id ?? null
  }

  return responsive?.[view]?.id ?? responsive?.desktop?.id ?? props.fieldValue?.banner_id ?? null
}

const bannerDataForView = (view: 'mobile' | 'desktop') => embeddedBannerData(resolveBannerId(view))

const imageRatioForView = (view: 'mobile' | 'desktop'): number | null => {
  const image = bannerDataForView(view)?.compiled_layout?.components?.[0]?.image
  const variant = image?.[view] ?? image?.desktop

  if (variant?.width > 0 && variant?.height > 0) {
    return variant.width / variant.height
  }

  return null
}


const bannerStyleForView = (view: 'mobile' | 'desktop'): Record<string, string> => {
  const banner = bannerDataForView(view)
  const bannerType = banner?.compiled_layout?.type
    ?? props.fieldValue?.compiled_layout?.type
    ?? 'landscape'

  let style: Record<string, string>

  if (bannerType === 'square') {
    style = view === 'mobile'
      ? { width: '100%', aspectRatio: '1 / 1' }
      : { width: '100%', height: SQUARE_BANNER_HEIGHT }
  } else if (view === 'mobile') {
    const ratio = imageRatioForView('mobile')

    style = ratio && ratio < LANDSCAPE_RATIO_THRESHOLD
      ? { width: MOBILE_BANNER_WIDTH, maxWidth: '100%', aspectRatio: `${ratio}` }
      : { width: '100%', height: MOBILE_BANNER_HEIGHT }
  } else {
    const [w, h] = (banner?.ratio ?? '4/1').split('/').map(Number)

    style = {
      width: '100%',
      aspectRatio: w > 0 && h > 0 ? `${w} / ${h}` : '4 / 1',
    }
  }

  const dimensions = getStyles(props.fieldValue?.banner_dimension?.properties, view, false) || {}
  if (dimensions.width) {
    style.width = dimensions.width
  }
  // Percentage heights resolve against a parent with no height (= nothing), which
  // collapses the reservation; keep the ratio/fallback geometry in that case.
  if (dimensions.height && !String(dimensions.height).trim().endsWith('%')) {
    style.height = dimensions.height
    delete style.aspectRatio
  }

  return style
}

const bannerBoxVars = computed<Record<string, string>>(() => {
  const vars: Record<string, string> = {}
  const keys: Record<string, string> = { width: 'w', maxWidth: 'mw', height: 'h', aspectRatio: 'ar' }

  for (const [view, suffix] of [['mobile', 'm'], ['desktop', 'd']] as const) {
    const style = bannerStyleForView(view)
    for (const [key, short] of Object.entries(keys)) {
      if (style[key]) {
        vars[`--bb-${short}-${suffix}`] = style[key]
      }
    }
  }

  return vars
})


const getDataBanner = async (): Promise<void> => {
  if (typeof window === "undefined") return;

  if (!activeId.value) {
    data.value = null;
    return;
  }

  const embedded = embeddedBannerData(activeId.value);
  if (embedded) {
    data.value = embedded;
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
    <div v-if="activeId && !data" class="banner-box mx-auto" :style="bannerBoxVars">
      <div class="skeleton w-full h-full"></div>
    </div>

    <section v-else-if="data" class="banner-box relative mx-auto" :style="bannerBoxVars">
      <div class="w-full h-full" :style="{
        ...getStyles(layout?.app?.webpage_layout?.container?.properties, effectiveScreenType),
        ...getStyles(fieldValue.container?.properties, effectiveScreenType),
      }">

        <div v-if="data?.compiled_layout?.type === 'landscape'" class="mx-auto w-full h-full"
          :class="bannerRatio !== '4/1' && 'max-w-full sm:max-w-2xl md:max-w-4xl lg:max-w-6xl xl:max-w-[1600px]'">
          <SliderLandscape :data="data.compiled_layout" :production="true" :view="effectiveScreenType" :ratio="bannerRatio" />
        </div>

        <SliderSquare v-else-if="data?.compiled_layout?.type === 'square'" :data="data.compiled_layout"
          :production="true" :view="effectiveScreenType" :ratio="bannerRatio" />
      </div>

    </section>

  </div>
</template>

