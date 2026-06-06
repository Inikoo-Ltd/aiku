<script setup lang="ts">
import { computed, inject, ref } from "vue"
import { getStyles } from "@/Composables/styles"
import { ctrans } from "@/Composables/useTrans"
import About from "@/Iris/Components/BlocksUtils/FamilyExtraDescription2/About.vue"
import MarketingMaterials from "@/Iris/Components/BlocksUtils/FamilyExtraDescription2/MarketingMaterials.vue"
import Faq from "@/Iris/Components/BlocksUtils/FamilyExtraDescription2/Faq.vue"

const props = defineProps<{
  fieldValue: any
  screenType: "mobile" | "tablet" | "desktop"
  indexBlock: number
}>()

const layout = inject("layout", {}) as any
const activeTab = ref("about")
const tabs = computed(() =>
  [
    { key: "about", label: ctrans("About the Range") },
    { key: "marketing", label: ctrans("Marketing Materials") },
    { key: "faq", label: ctrans("FAQ") },
  ].filter(tab => {
    if (tab.key === "marketing") {
      return layout?.iris?.is_logged_in
    }

    if (tab.key === "faq") {
      return (
        Array.isArray(props.fieldValue?.family?.faq) &&
        props.fieldValue.family.faq.length > 0
      )
    }

    return true
  })
)
const sectionId = computed(
  () => props.fieldValue?.id ?? `family-1-iris-${props.indexBlock}`,
)

const containerStyle = computed(() => ({
  ...getStyles(layout?.app?.webpage_layout?.container?.properties, props.screenType),
  ...getStyles(props.fieldValue?.container?.properties),
  width: "auto",
}))

const component = (tab: string) => {
  switch (tab) {
    case "about":
      return About
    case "marketing":
      return MarketingMaterials
    case "faq":
      return Faq
    default:
      return null
  }
}

const sectionStyle = computed(() => {
  const bg = props.fieldValue?.container?.properties?.background[props.screenType]

  return {
    backgroundColor: bg?.color || undefined,
    backgroundImage: bg?.image?.original
      ? `url(${bg.image.original})`
      : undefined,
    backgroundSize: "cover",
    backgroundPosition: "center",
  }
})

const isMobile = computed(() => props.screenType === "mobile")

</script>

<template>
  <section  class="w-full bg-[#D8D9DB]" :id="sectionId"   :style="sectionStyle">
    <div class="mx-auto w-full max-w-[1700px]  px-4 py-4 sm:px-8 xl:px-14 2xl:max-w-[1800px] 2xl:px-14"
      :style="containerStyle">
      <!-- TOP NAV -->
      <div class="border-b border-[#9a9a9a]">
        <!-- Mobile -->
        <div v-if="isMobile" class="py-3">
          <select v-model="activeTab"
            class="w-full rounded-md border border-[#d9d9d9] bg-transparent px-4 py-3 text-[13px] focus:outline-none">
            <option v-for="tab in tabs" :key="tab.key" :value="tab.key">
              {{ tab.label }}
            </option>
          </select>
        </div>

        <!-- Tablet & Desktop -->
        <div v-else
          class="flex flex-wrap items-center justify-center lg:justify-end gap-3 md:gap-6 lg:gap-10 2xl:gap-14">
          <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key"
            class="relative -mb-px border-b px-1 md:px-2 py-3 md:py-4 text-[10px] sm:text-[11px] md:text-[12px] transition-all duration-200"
            :class="activeTab === tab.key
                ? 'border-primary text-primary'
                : 'border-transparent text-[#9a9a9a]'
              ">
            {{ tab.label }}
          </button>
        </div>
      </div>

      <!-- CONTENT -->
      <component :is="component(activeTab)" :field-value="fieldValue" :screen-type="screenType" />
    </div>
  </section>


</template>

<style scoped>
:deep(p) {
  margin-bottom: 18px;
}

:deep(p:last-child) {
  margin-bottom: 0;
}

:deep(h2),
:deep(h3),
:deep(h4),
:deep(h5),
:deep(h6) {
  margin-top: 18px;
  margin-bottom: 12px;
  font-weight: 500;
  color: #22374a;
}

:deep(ul),
:deep(ol) {
  margin-bottom: 18px;
  padding-left: 20px;
}

:deep(li) {
  margin-bottom: 6px;
}

:deep(img) {
  max-width: 100%;
}
</style>