<script setup lang="ts">
import { ref, computed, watch } from "vue";
import { ulid } from "ulid"
import { faCube, faLink } from "@fal";
import { faStar, faCircle } from "@fas";
import { faChevronCircleLeft, faChevronCircleRight } from "@far";
import { library } from "@fortawesome/fontawesome-svg-core";
import ColumnWebppage from "./ColumnWebppageIris.vue"
import { getStyles } from "@/Composables/styles"


library.add(
    faCube,
    faLink,
    faStar,
    faCircle,
    faChevronCircleLeft,
    faChevronCircleRight
);

interface TabItem {
    id: string;
    name: string;
    data?: {
        fieldValue?: any;
    };
}

interface ModelValue {
    tabs: TabItem[];
}

const props = defineProps<{
    fieldValue: ModelValue;
    blockData?: Record<string, any>;
    screenType: "mobile" | "tablet" | "desktop";
    indexBlock?: number;
    webpageData?: any
}>();

const activeTab = ref(0);

const currentTab = computed(() => {
    return props.fieldValue.tabs?.[activeTab.value];
});

const emits = defineEmits<{
    (e: "update:modelValue", value: string): void
    (e: "autoSave"): void
}>()

const key = ref(ulid())

watch(
    () => props.screenType,
    () => {
        key.value = ulid()
    }
)
</script>

<template>
    <div :id="fieldValue?.id ? fieldValue?.id : 'Tabs' + indexBlock" class="w-full pb-6 md:px-[50px]" :style="{
        ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
        ...getStyles(fieldValue.container?.properties, screenType),
        width: 'auto'
    }">

        <div v-if="fieldValue?.tabs?.length">
            <!-- Tab Headers -->
            <div class="flex border-b">
                <button v-for="(tab, index) in fieldValue.tabs" :key="tab.id" @click="activeTab = index"
                    class="px-4 py-2 border-b-2 transition" :class="activeTab === index
                        ? 'border-primary text-primary font-semibold'
                        : 'border-transparent'
                        ">
                    {{ tab.name }}
                </button>
            </div>

            <!-- Tab Content -->
            <div class="p-4">
                <ColumnWebppage :fieldValue="currentTab" :screenType="screenType" :key="`col-1-${key}`" />
            </div>
        </div>
    </div>
</template>

<style scoped></style>