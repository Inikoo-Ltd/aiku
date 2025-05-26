<script setup lang="ts">
import { getComponent } from "@/Composables/getWorkshopComponents"
import { ref, onMounted, onBeforeUnmount, watch } from "vue"
import WebPreview from "@/Layouts/WebPreview.vue"
import EmptyState from "@/Components/Utils/EmptyState.vue"
import { sendMessageToParent } from "@/Composables/Workshop"
import { router, Head } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faSendBackward, faBringForward, faTrashAlt } from "@fas"
import { useConfirm } from "primevue/useconfirm"
import "@/../css/Iris/editor.css"
import { Root as RootWebpage } from "@/types/webpageTypes"
import { trans } from "laravel-vue-i18n"

defineOptions({ layout: WebPreview })

const props = defineProps<{
  webpage?: RootWebpage
  header: { data: {} }
  footer: { footer: {} }
  navigation: { menu: {} }
  layout: {}
}>()

const confirm = useConfirm()

const filterBlock = ref<'all' | 'logged-in' | 'logged-out'>('all')
const isPreviewMode = ref(false)
const activeBlock = ref<number | null>(null)
const screenType = ref<'mobile' | 'tablet' | 'desktop'>('desktop')


const showWebpage = (activityItem) => {
	if (activityItem?.web_block?.layout && activityItem.show) {
		if (filterBlock.value == 'all' ) return true 
		else if (filterBlock.value == 'logged-out' && activityItem.visibility.out) return true
		else if (filterBlock.value  == 'logged-in' && activityItem.visibility.in) return true
	} else return false
}

const checkScreenType = () => {
  const width = window.innerWidth
  if (width < 640) screenType.value = 'mobile'
  else if (width < 1024) screenType.value = 'tablet'
  else screenType.value = 'desktop'
}

const updateData = (newVal: any) => {
  sendMessageToParent("autosave", newVal)
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
  if (key === "reload") {
    router.reload({ only: ["footer", "header", "webpage"] })
  }
}

onMounted(() => {
  window.addEventListener("message", handleMessage)
  checkScreenType()
  window.addEventListener("resize", checkScreenType)
})

onBeforeUnmount(() => {
  window.removeEventListener("resize", checkScreenType)
  window.removeEventListener("message", handleMessage)
})
</script>

<template>
  <Head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  </Head>

  <div class="editor-class">
    <div class="shadow-xl px-1">
      <div v-if="webpage">
        <div v-if="props.webpage?.layout?.web_blocks.length">
          <TransitionGroup tag="div" name="list" class="relative">
            <template v-for="(activityItem, idx) in props.webpage?.layout?.web_blocks" :key="activityItem.id">
              <section
			  	v-show="showWebpage(activityItem)"
                class="w-full min-h-[50px] relative"
                :data-block-id="idx"
                :class="{
                  'border-4 border-[#4F46E5] active-block': activeBlock === idx,
                }"
                @click="() => sendMessageToParent('activeBlock', idx)"
              >
                <div v-if="activeBlock === idx" class="trapezoid-button" @click.stop>
                  <div class="flex">
                    <div
                      class="py-1 px-2 cursor-pointer hover:bg-gray-200 transition hover:text-indigo-500"
                      v-tooltip="trans('Add Block Before')"
                      @click="() => sendMessageToParent('addBlock', { type: 'before', parentIndex: idx })"
                    >
                      <FontAwesomeIcon :icon="faSendBackward" fixed-width />
                    </div>

                    <div
                      class="py-1 px-2 cursor-pointer hover:bg-gray-200 hover:text-indigo-500 transition md:block hidden"
                      v-tooltip="trans('Add Block After')"
                      @click="() => sendMessageToParent('addBlock', { type: 'after', parentIndex: idx })"
                    >
                      <FontAwesomeIcon :icon="faBringForward" fixed-width />
                    </div>

                    <div
                      class="py-1 px-2 cursor-pointer hover:bg-red-100 hover:text-red-600 transition"
                      v-tooltip="trans('Delete')"
                      @click="() => sendMessageToParent('deleteBlock', activityItem)"
                    >
                      <FontAwesomeIcon :icon="faTrashAlt" fixed-width />
                    </div>
                  </div>
                </div>

                <component
                  class="w-full"
                  :is="getComponent(activityItem.type)"
                  :webpageData="webpage"
                  :blockData="activityItem"
                  @autoSave="() => updateData(activityItem)"
                  v-model="activityItem.web_block.layout.data.fieldValue"
                  :screenType="screenType"
                />
              </section>
            </template>
          </TransitionGroup>
        </div>

        <EmptyState
          v-else
          :data="{
            title: trans('Pick First Block For Your Website'),
            description: trans('Pick block from list'),
          }"
        />
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.hover-dashed {
  @apply relative;

  &::after {
    content: "";
    @apply absolute inset-0 hover:bg-gray-200/30 border border-transparent hover:border-white/80 border-dashed cursor-pointer;
  }
}

.trapezoid-button {
  position: absolute;
  top: -37px;
  left: 50%;
  transform: translateX(-50%);
  padding: 5px 20px;
  background-color: #4F46E5;
  color: white;
  font-size: 12px;
  font-weight: bold;
  cursor: pointer;
  clip-path: polygon(15% 0%, 85% 0%, 100% 100%, 0% 100%);
  transition: background 0.3s;
  box-shadow: 0 4px 0px #4F46E5;
  border: none;
  z-index: 99;
}

.trapezoid-button:hover {
  background-color: #3F3ABF;
}
</style>
