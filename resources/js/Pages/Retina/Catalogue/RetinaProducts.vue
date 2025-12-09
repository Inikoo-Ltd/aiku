<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import { capitalize } from "@/Composables/capitalize";
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading";
import { library } from "@fortawesome/fontawesome-svg-core";
import { faSyncAlt } from "@fas";
import {
    faBracketsCurly,
    faFileExcel,
    faImage,
    faArrowLeft,
    faArrowRight,
    faUpload,
    faBox,
} from "@fal";
import RetinaTableProducts from "@/Components/Tables/Retina/RetinaTableProducts.vue";

library.add(faFileExcel, faBracketsCurly, faImage, faSyncAlt, faBox, faArrowLeft, faArrowRight, faUpload);

defineProps<{
    title: string
    pageHead: PageHeadingTypes
    data : {}
}>();



</script>

<template>

    <Head :title="capitalize(title)" />
    <RetinaTableProducts :data="data"/>
</template>
