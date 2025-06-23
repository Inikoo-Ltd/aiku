<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, provide, shallowRef, watch, toRaw } from "vue"
import { router } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faSendBackward, faBringForward, faTrashAlt } from "@fas"
import { trans } from "laravel-vue-i18n"
import { useLayoutStore } from "@/Stores/layout"

import WebPreview from "@/Layouts/WebPreview.vue"
import EmptyState from "@/Components/Utils/EmptyState.vue"
import { getComponent } from "@/Composables/getWorkshopComponents"
import { sendMessageToParent } from "@/Composables/Workshop"
import { getStyles } from "@/Composables/styles"

import { Root as RootWebpage } from "@/types/webpageTypes"
import "@/../css/Iris/editor.css"

defineOptions({ layout: WebPreview })

const props = defineProps<{
  webpage?: RootWebpage
  layout: {}
}>()

const layout = useLayoutStore()

const data = shallowRef<RootWebpage | undefined>(toRaw(props.webpage))

const filterBlock = ref<'all' | 'logged-in' | 'logged-out'>('all')
const isPreviewMode = ref(false)
const activeBlock = ref<number | null>(null)
const screenType = ref<'mobile' | 'tablet' | 'desktop'>('desktop')

const showWebpage = (item) => {
  const vis = item?.visibility
  const layout = item?.web_block?.layout
  if (!layout || !item.show) return false
  if (filterBlock.value === 'all') return true
  if (filterBlock.value === 'logged-out' && vis?.out) return true
  if (filterBlock.value === 'logged-in' && vis?.in) return true
  return false
}

const checkScreenType = () => {
  const width = window.innerWidth
  screenType.value = width < 640 ? 'mobile' : width < 1024 ? 'tablet' : 'desktop'
}

const updateData = (val: any) => {
  sendMessageToParent("autosave", val)
}

const handleMessage = (event: MessageEvent) => {
  const { key, value } = event.data

  if (key === "isPreviewLoggedIn") filterBlock.value = value
  if (key === "isPreviewMode") isPreviewMode.value = value
  if (key === "activeBlock") {
    activeBlock.value = value
    const el = document.querySelector(`[data-block-id="${value}"]`)
    if (el) el.scrollIntoView({ behavior: "smooth", block: "center" })
  }
  if (key === "reload") reloadPage()

  // ✅ Accept new webpage from iframe message
  if (key === "setWebpage") data.value = value
}

const reloadPage = () => {
  router.reload({ only: ["webpage"] })
  console.log('dsdd',props.webpage)
  data.value = {...props.webpage}
}
provide("reloadPage", reloadPage)

onMounted(() => {
  window.addEventListener("message", handleMessage)
  window.addEventListener("resize", checkScreenType)
  checkScreenType()
})

onBeforeUnmount(() => {
  window.removeEventListener("resize", checkScreenType)
  window.removeEventListener("message", handleMessage)
})
</script>

<template>
  <pre>{{ data?.layout?.web_blocks }}</pre>
  <div class="editor-class" :style="getStyles(layout.container?.properties, screenType)">
    <div class="shadow-xl px-1">
      <div>
        <div v-if="data?.layout?.web_blocks?.length">
          <TransitionGroup tag="div" name="list" class="relative">
            <template v-for="(block, idx) in data.layout.web_blocks" :key="block.id">
              <section v-show="showWebpage(block)" :data-block-id="idx" class="w-full min-h-[50px] relative"
                :class="{ 'border-4 active-block': activeBlock === idx }"
                :style="activeBlock === idx ? { borderColor: layout?.app?.theme[0] } : {}"
                @click="() => sendMessageToParent('activeBlock', idx)">
                <!-- Toolbar Controls -->
                <div v-if="activeBlock === idx" class="trapezoid-button" @click.stop>
                  <div class="flex">
                    <div v-tooltip="trans('Add Block Before')"
                      class="py-1 px-2 cursor-pointer hover:bg-gray-200 transition"
                      @click="() => sendMessageToParent('addBlock', { type: 'before', parentIndex: idx })">
                      <FontAwesomeIcon :icon="faSendBackward" fixed-width />
                    </div>

                    <div v-tooltip="trans('Add Block After')"
                      class="py-1 px-2 cursor-pointer hover:bg-gray-200 transition md:block hidden"
                      @click="() => sendMessageToParent('addBlock', { type: 'after', parentIndex: idx })">
                      <FontAwesomeIcon :icon="faBringForward" fixed-width />
                    </div>

                    <div v-tooltip="trans('Delete')"
                      class="py-1 px-2 cursor-pointer hover:bg-red-100 hover:text-red-600 transition"
                      @click="() => sendMessageToParent('deleteBlock', block)">
                      <FontAwesomeIcon :icon="faTrashAlt" fixed-width />
                    </div>
                  </div>
                </div>

                <!-- Dynamic Block -->
                <component :is="getComponent(block.type)" class="w-full" :webpageData="data" :blockData="block"
                  v-model="block.web_block.layout.data.fieldValue" :screenType="screenType"
                  @autoSave="() => updateData(block)" />
              </section>
            </template>
          </TransitionGroup>
        </div>

        <EmptyState v-else :data="{
            title: trans('Pick First Block For Your Website'),
            description: trans('Pick block from list'),
          }" />
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
:deep(.hover-dashed) {
  @apply relative;

  &::after {
    content: "";
    @apply absolute inset-0 hover:bg-gray-200/30 border border-transparent hover:border-white/80 border-dashed cursor-pointer;
  }
}

:deep(.hover-text-input) {
  @apply relative isolate;

  &::after {
    content: "";
    @apply -z-10 absolute inset-0 hover:bg-gray-200/30 border border-transparent hover:border-white/80 border-dashed cursor-pointer;
  }
}

.trapezoid-button {
  @apply absolute z-[99] top-[-37px] left-1/2 px-5 py-1 text-white text-xs font-bold transition;
  transform: translateX(-50%);
  background-color: v-bind('layout?.app?.theme[0]') !important;
  clip-path: polygon(15% 0%, 85% 0%, 100% 100%, 0% 100%);
  box-shadow: 0 4px 0px v-bind('layout?.app?.theme[0]') !important;
  border: none;

  &:hover {
    background-color: v-bind('layout?.app?.theme[0]') !important;
  }
}
</style>
