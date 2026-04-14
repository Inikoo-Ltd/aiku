<script setup lang="ts">
import { ref, computed, watch, nextTick } from "vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { TabGroup, TabList, Tab, TabPanels, TabPanel } from "@headlessui/vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

import WebBlockListDnd from "@/Components/CMS/Fields/WebBlockListDnd.vue"
import SideEditor from "@/Components/Workshop/SideEditor/SideEditor.vue"
import { getBlueprint } from "@/Composables/getBlueprintWorkshop"

import { faThLarge, faPaintBrushAlt, faCog } from "@fas"

library.add(faThLarge, faPaintBrushAlt)

const props = defineProps<{
  data: any
  webBlockTypes: { data: any[] }
  selectedBlock: any
}>()

const emits = defineEmits<{
  (e: "setUpTemplate", value: string | number): void
  (e: "autoSave"): void
  (e: "update:selectedBlock", value: any): void
}>()

/* const selectedBlock = ref<any>(null) */
const selectedTab = ref(0)


const tabsWithPanels = computed(() => {
  const items: any[] = []

  // 1. Templates
  items.push({
    label: "Templates",
    icon: faThLarge,
    type: "templates",
  })

  // 2. Settings
  if (props.data) {
    items.push({
      label: "Settings",
      icon: faCog,
      type: "settings",
    })
  }

  // 3. Editor
  if (props.selectedBlock) {
    items.push({
      label: "Editor",
      icon: faPaintBrushAlt,
      type: "editor",
    })
  }

  return items
})


watch(tabsWithPanels, (tabs) => {
  if (selectedTab.value >= tabs.length) {
    selectedTab.value = tabs.length - 1
  }
})



function changeTab(i: number) {
  selectedTab.value = i
}

async function onPickBlock(value: object) {
  emits("setUpTemplate", value)

  await nextTick()

  selectedTab.value = tabsWithPanels.value.findIndex(
    (t) => t.type === "settings"
  )
}

async function onSelectBlock(block: any, key: string) {
  emits("update:selectedBlock", { ...block, code: key })

  await nextTick()

  selectedTab.value = tabsWithPanels.value.findIndex(
    (t) => t.type === "editor"
  )
}

function getFirstEntry(data: any) {
  const entries = Object.entries(data || {})
  if (!entries.length) return { key: null }
  return { key: entries[0][0] }
}

function onUpdateFieldValue(e: any) {
  if (!props.selectedBlock?.code) return

  const code = props.selectedBlock.code

  emits("update:selectedBlock", {
    ...props.selectedBlock,
    fieldValue: e
  })

  if (props.data?.[code]) {
    props.data[code].fieldValue = e
  }

  emits("autoSave")
}
</script>

<template>
  <div class="h-full flex flex-col">
    <TabGroup as="div" :selectedIndex="selectedTab" @change="changeTab" class="flex flex-col h-full">

      <div class="flex border-b bg-gray-50">
        <Tab v-for="(tab, index) in tabsWithPanels" :key="index"
          class="flex w-fit items-center gap-2 px-4 py-2 font-medium text-gray-600 hover:bg-gray-100 focus:outline-none"
          :class="{
            'bg-white text-indigo-600 border-b-2 border-indigo-600':
              selectedTab === index,
          }">
          <FontAwesomeIcon :icon="tab.icon" fixed-width v-tooltip="tab.tooltip" />
        </Tab>
      </div>


      <!-- Panels (SYNCED) -->
      <TabPanels class="flex-grow overflow-auto bg-gray-50">
        <TabPanel v-for="(tab, i) in tabsWithPanels" :key="i">
          <!-- TEMPLATES -->
          <template v-if="tab.type === 'templates'">
            <WebBlockListDnd :webBlockTypes="webBlockTypes" @pick-block="onPickBlock"
              :selectedWeblock="getFirstEntry(data).key" />
          </template>

          <!-- SETTINGS -->
          <template v-else-if="tab.type === 'settings'">
            <div v-for="(block, key) in data" :key="key" @click="onSelectBlock(block, key)"
              class="p-2 text-xs mb-2 mt-1 cursor-pointer rounded transition-all duration-150" :class="[
                key === selectedBlock?.code
                  ? 'card-active '
                  : 'border border-gray-200 bg-white hover:border-gray-400'
              ]">
              {{ key }}
            </div>
          </template>

          <!-- EDITOR -->
          <template v-else-if="tab.type === 'editor'">
            <SideEditor :modelValue="selectedBlock?.fieldValue" :blueprint="getBlueprint(selectedBlock?.code)"
              @update:modelValue="onUpdateFieldValue" :uploadImageRoute="null" />
          </template>
        </TabPanel>
      </TabPanels>
    </TabGroup>
  </div>
</template>

<style scoped>
.h-full {
  height: 100%;
}

.card-active {
  color: var(--theme-color-4) !important;
  border: 1px solid color-mix(in srgb, var(--theme-color-4) 80%, black);
}
</style>