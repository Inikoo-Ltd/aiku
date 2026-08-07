<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, provide, shallowRef, watch, toRaw, inject } from "vue"
import { router } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faSendBackward, faBringForward, faTrashAlt } from "@fas"
import { trans } from "laravel-vue-i18n"

import WebPreview from "@/Layouts/WebPreview.vue"
import EmptyState from "@/Components/Utils/EmptyState.vue"
import { getComponent } from "@/Composables/getWorkshopComponents"
import { sendMessageToParent } from "@/Composables/Workshop"
import { faTimes } from "@fal"
import {debounce} from "lodash-es"

import { Root as RootWebpage } from "@/types/webpageTypes"
import "@/../css/Iris/editor.css"
import { ulid } from "ulid"

import { library } from "@fortawesome/fontawesome-svg-core";
import { setColorStyleRoot } from "@/Composables/useApp"

library.add(faTimes);


defineOptions({ layout: WebPreview })

const props = defineProps<{
  webpage?: RootWebpage
  layout: {
    color: string[]
  }
  luigisbox_tracker_id?: string
  editable : boolean
}>()
let layout: any = inject("layout", {});
const data = shallowRef<RootWebpage | undefined>(toRaw(props.webpage))
const filterBlock = ref<'all' | 'logged-in' | 'logged-out'>('all')
const isPreviewMode = ref(false)
const activeBlock = ref<number | null>(null)
const screenType = ref<'mobile' | 'tablet' | 'desktop'>('desktop')
const key = ref(ulid())
const blockRefs = new Map<number, HTMLElement>()
const blockRefSetters = new Map<number, (el: HTMLElement | null) => void>()
const isActiveBlockNearTop = ref(false)

const DEFAULT_CURRENCY = {
  code: "GBP",
  symbol: "£",
  name: "British Pound"
}

// Update iris layout state
const updateIrisLayout = () => {
  layout.iris = {
    currency: DEFAULT_CURRENCY,
    is_logged_in: filterBlock.value === "logged-in",
    luigisbox_tracker_id: props.luigisbox_tracker_id
  }
}

const showWebpage = (item) => {
  const vis = item?.visibility
  const layoutBlock = item?.web_block?.layout
  if (!layoutBlock || !item.show) return false
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

const debouncedSetWebpage = debounce((value: any) => {
  data.value = value
}, 1000)

const onResize = debounce(checkScreenType, 150)

const handleMessage = (event: MessageEvent) => {
  const { key: messageKey, value } = event.data

  switch (messageKey) {
    case "isPreviewLoggedIn":
      filterBlock.value = value
      return
    case "isPreviewMode":
      isPreviewMode.value = value
      return
    case "activeBlock": {
      const el = blockRefs.get(Number(value))
      // Measured before the toolbar renders so it never flips position mid-frame
      isActiveBlockNearTop.value = el ? el.getBoundingClientRect().top < 80 : false
      activeBlock.value = value
      el?.scrollIntoView({ behavior: "smooth", block: "center" })
      return
    }
    case "reload":
      reloadPage()
      return
    case "setWebpage":
      debouncedSetWebpage(value)
      return
    case "layoutSetup":
      layout = { ...layout, ...value }
  }
}

const reloadPage = (withkey = false) => {
  router.reload({ only: ["webpage"] })
  if (withkey) key.value = ulid()
}

const getBlockRefSetter = (idx: number) => {
  let setter = blockRefSetters.get(idx)
  if (!setter) {
    setter = (el: HTMLElement | null) => {
      if (el) blockRefs.set(idx, el)
      else blockRefs.delete(idx)
    }
    blockRefSetters.set(idx, setter)
  }
  return setter
}

provide("reloadPage", reloadPage)

onMounted(() => {
  if (props.layout?.color) {
    layout.app.theme = props.layout.color
    layout.app.webpage_layout = props.layout
  }
  window.addEventListener("message", handleMessage)
  window.addEventListener("resize", onResize, { passive: true })
  checkScreenType()
  setColorStyleRoot(props.layout?.color)
})

onBeforeUnmount(() => {
  onResize.cancel()
  debouncedSetWebpage.cancel()
  window.removeEventListener("resize", onResize)
  window.removeEventListener("message", handleMessage)
})

watch(() => props.webpage, (val) => {
  data.value = val ? { ...val } : undefined
})

watch(filterBlock, updateIrisLayout, { immediate: true })

</script>

<template>
  <div class="editor-class">
    <div class="shadow-xl px-1 py-1">
      <div>
        <div v-if="data?.layout?.web_blocks?.length">
          <TransitionGroup tag="div" name="list" class="relative">
            <section v-for="(block, idx) in data.layout.web_blocks" :key="block.id"
              v-show="showWebpage(block)"
              :data-block-id="idx"
              :ref="getBlockRefSetter(idx)"
              class="w-full min-h-[10px] relative"
              :class="{ 'border-4 active-block': activeBlock === idx }"
              :style="activeBlock === idx ? { borderColor: layout?.app?.theme?.[0] } : {}"
              @click="sendMessageToParent('activeBlock', idx)">
              <!-- Toolbar Controls -->
              <div v-if="activeBlock === idx" class="trapezoid-button"
                :class="{ 'trapezoid-bottom': isActiveBlockNearTop }" @click.stop>
                <div class="flex" v-if="editable">
                  <div v-tooltip="trans('Add Block Before')"
                    class="py-1 px-2 cursor-pointer hover:bg-gray-200 transition"
                    @click="sendMessageToParent('addBlock', { type: 'before', parentIndex: idx })">
                    <FontAwesomeIcon :icon="faSendBackward" fixed-width />
                  </div>

                  <div v-tooltip="trans('Add Block After')"
                    class="py-1 px-2 cursor-pointer hover:bg-gray-200 transition md:block hidden"
                    @click="sendMessageToParent('addBlock', { type: 'after', parentIndex: idx })">
                    <FontAwesomeIcon :icon="faBringForward" fixed-width />
                  </div>

                  <div v-tooltip="trans('Delete')"
                    class="py-1 px-2 cursor-pointer hover:bg-red-100 hover:text-red-600 transition"
                    @click="sendMessageToParent('deleteBlock', block)">
                    <FontAwesomeIcon :icon="faTrashAlt" fixed-width />
                  </div>
                </div>
              </div>

              <!-- Dynamic Block -->
              <div :class="!editable ? 'pointer-events-none select-none' : ''">
                <component
                  :code="block.type"
                  :is="getComponent(block.type, { shop_type: layout?.retina?.type })"
                  class="w-full"
                  :webpageData="data"
                  :blockData="block"
                  :index-block="idx"
                  :key="key"
                  v-model="block.web_block.layout.data.fieldValue"
                  :screenType="screenType"
                  @autoSave="updateData(block)"
                />
              </div>
            </section>
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

<style lang="scss">
.trapezoid-button {
  @apply absolute  left-1/2 px-5 py-1 text-white text-xs font-bold transition;
  transform: translateX(-50%);
  background-color: v-bind('layout?.app?.theme?.[0]') !important;
  clip-path: polygon(15% 0%, 85% 0%, 100% 100%, 0% 100%);
  box-shadow: 0 4px 0px v-bind('layout?.app?.theme?.[0]') !important;
  border: none;
  top: -37px;

  &:hover {
    background-color: v-bind('layout?.app?.theme?.[0]') !important;
  }
}

/* jika index === 1 → pindah ke bawah */
.trapezoid-bottom {
  top: auto;
  bottom: -37px;
  clip-path: polygon(0% 0%, 100% 0%, 85% 100%, 15% 100%);
  box-shadow: 0 -4px 0px v-bind('layout?.app?.theme?.[0]') !important;
}

#jsd-widget {
  display: none !important;
}

#iris_breadcrumbs ol,
#iris_breadcrumbs ul {
    margin-left: 0;
    margin-top: 0;
    list-style-position: outside;
}

#iris_breadcrumbs ol li,
#iris_breadcrumbs ul li {
    margin-left: 0;
    margin-top: 0;
    padding-left: 0;
    padding-top: 0;
}

.vue-notification-group {
    width: 300px !important;

    @media (min-width: 640px) {
        width: 500px !important;
    }
}

.basket-drawer {
    width: min(92vw, 37%);
    box-sizing: border-box;
}

@media (min-width: 1536px) {
    .basket-drawer {
        width: 25%;
    }
}

// INI-562: live chat
iframe#launcher {
    bottom: 30px !important;
}

.background-primary {
    background-color: var(--theme-color-4);
}

.border-primary {
    border-color: var(--theme-color-4);
}

.text-primary {
    color: var(--theme-color-4) !important;
}

.primaryLink {
    background: v-bind('`linear-gradient(to top, #fcd34d, #fcd34d)`');

    &:hover,
    &:focus {
        color: #374151;
    }

    @apply focus:ring-0 focus:outline-none focus:border-none bg-no-repeat [background-position:0%_100%] [background-size:100%_0.2em] motion-safe:transition-all motion-safe:duration-200 hover:[background-size:100%_100%] focus:[background-size:100%_100%] px-1 py-0.5
}



</style>
