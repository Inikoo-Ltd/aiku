<script setup lang="ts">
import { faCube, faLink, faImage } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import ColumnWebppage from "@/Components/CMS/Webpage/WorkshopComponentsHelper/ColumnWebppageIris.vue";
import { getStyles } from "@/Composables/styles"

library.add(faCube, faLink, faImage)

const props = defineProps<{
    fieldValue: any
    screenType: "mobile" | "tablet" | "desktop"
}>()

</script>

<template>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-center"
        :style="getStyles(fieldValue?.container?.properties,screenType)"
    >
        <!-- <pre>{{ fieldValue }}</pre> -->
        <ColumnWebppage :fieldValue="fieldValue.column_1" :screenType="screenType" />
        <ColumnWebppage :fieldValue="fieldValue.column_2" :screenType="screenType"/>
        <ColumnWebppage :fieldValue="fieldValue.column_3" :screenType="screenType"/>
        <ColumnWebppage :fieldValue="fieldValue.column_4" :screenType="screenType"/>
    </div>
</template>
