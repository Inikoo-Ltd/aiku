<script setup lang="ts">
import { computed, inject, ref } from "vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCube, faLink, faInfoCircle } from "@fal"
import { faStar, faCircle, faBadgePercent } from "@fas"
import { faChevronCircleLeft, faChevronCircleRight } from "@far"
import { ctrans } from "@/Composables/useTrans"
import { getStyles } from "@/Composables/styles"
import About from "@/Components/CMS/Webpage/Family2ExtraDescription/AboutWorkshop.vue"
import FaqWorkshop from "./FaqWorkshop.vue"
import MarketingMaterialsWorkshop from "./MarketingMaterialsWorkshop.vue"

library.add(
  faCube,
  faLink,
  faInfoCircle,
  faStar,
  faCircle,
  faBadgePercent,
  faChevronCircleLeft,
  faChevronCircleRight
)

const props = defineProps<{
  modelValue: any
  webpageData?: any
  blockData?: Object
  screenType: "mobile" | "tablet" | "desktop"
  indexBlock: number
}>()

const layout = inject("layout", {}) as any
const activeTab = ref("about")

const tabs = [
  { key: "about", label: ctrans("About the Range") },
  // { key: "retailers", label: ctrans("Notes For Retailers") },
  { key: "marketing", label: ctrans("Marketing Materials") },
  { key: "faq", label: ctrans("FAQ") },
].filter(tab => {
  if (tab.key === "faq") {
    return Array.isArray(props.modelValue?.family?.faq) && props.modelValue?.family?.faq.length > 0
  }

  return true
})

const sectionId = computed(
  () => props.modelValue?.id ?? `family-1-iris-${props.indexBlock}`
)

const containerStyle = computed(() => ({
  ...getStyles(
    layout?.app?.webpage_layout?.container?.properties,
    props.screenType
  ),
  ...getStyles(props.modelValue?.container?.properties),
  width: "auto",
}))

const component = (tab: string) => {
  switch (tab) {
    case "about":
      return About
    case "marketing":
      return MarketingMaterialsWorkshop
    case "faq":
      return FaqWorkshop
    default:
      return null
  }
}

/* Responsive Classes */

const containerClass = computed(() => {
  switch (props.screenType) {
    case "mobile":
      return "px-4 py-4"

    case "tablet":
      return "px-8 py-4"

    default:
      return "px-6 py-4"
  }
})

const tabsWrapperClass = computed(() => {
  switch (props.screenType) {
    case "mobile":
      return "justify-center gap-3"

    case "tablet":
      return "justify-center gap-6"

    default:
      return "justify-end gap-14"
  }
})

const tabButtonClass = computed(() => {
  switch (props.screenType) {
    case "mobile":
      return "px-1 py-3 text-[10px]"

    case "tablet":
      return "px-2 py-4 text-[12px]"

    default:
      return "px-2 py-4 text-[12px]"
  }
})
</script>

<template>
  <section
    class="w-full bg-[#D8D9DB] "
    :id="sectionId"
  >
    <div
      class="mx-auto w-full  bg-white editor-class"
      :class="containerClass"
      :style="containerStyle"
    >
      <!-- TOP NAV -->
      <div class="border-b border-[#9a9a9a]">
        <div
          class="flex flex-wrap items-center"
          :class="tabsWrapperClass"
        >
          <button
            v-for="tab in tabs"
            :key="tab.key"
            @click="activeTab = tab.key"
            class="relative -mb-px border-b transition-all duration-200"
            :class="[
              tabButtonClass,
              activeTab === tab.key
                ? 'border-primary text-primary'
                : 'border-transparent text-[#9a9a9a]'
            ]"
          >
            {{ tab.label }}
          </button>
        </div>
      </div>

      <!-- CONTENT -->
      <component
        :is="component(activeTab)"
        :field-value="modelValue"
        :screen-type="screenType"
      />
    </div>
  </section>
</template>