<script setup lang="ts">
import { faCube, faLink, faImage } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import ColumnWebppage from "@/Components/CMS/Webpage/WorkshopComponentsHelper/ColumnWebppageIris.vue";
import { getStyles } from "@/Composables/styles"
import { ulid } from "ulid"
import { ref, watch } from "vue"

library.add(faCube, faLink, faImage)

const props = defineProps<{
    fieldValue: any
    screenType: "mobile" | "tablet" | "desktop"
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
    <div :id="fieldValue?.id ? fieldValue?.id  : 'column-4'"  component="column-4"  class="grid grid-cols-1 md:grid-cols-4 gap-4 items-center"
        :style="getStyles(fieldValue?.container?.properties,screenType)"
    >
        <!-- <pre>{{ fieldValue }}</pre> -->
        <ColumnWebppage :fieldValue="fieldValue.column_1" :screenType="screenType"  :key="`col-1-${key}`"/>
        <ColumnWebppage :fieldValue="fieldValue.column_2" :screenType="screenType" 	:key="`col-2-${key}`"/>
        <ColumnWebppage :fieldValue="fieldValue.column_3" :screenType="screenType" 	:key="`col-3-${key}`"/>
        <ColumnWebppage :fieldValue="fieldValue.column_4" :screenType="screenType"	:key="`col-4-${key}`"/>
    </div>
</template>
