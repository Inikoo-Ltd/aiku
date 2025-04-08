<script setup lang="ts">
import { faCube, faLink, faImage } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import ColumnWebppage from "./ColumnWebppageIris.vue";

library.add(faCube, faLink, faImage)

const props = defineProps<{
    fieldValue: any
}>()

</script>

<template>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-white">
            <ColumnWebppage :fieldValue="fieldValue.column_1" />
        </div>
        <div class="bg-white">
            <ColumnWebppage :fieldValue="fieldValue.column_2" />
        </div>
    </div>
</template>
