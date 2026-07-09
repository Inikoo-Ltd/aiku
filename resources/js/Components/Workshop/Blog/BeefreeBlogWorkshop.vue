<script setup lang="ts">
import { ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import Beetree from '@/Components/CMS/Website/Outboxes/Beefree.vue'
import { PageHeadingTypes } from "@/types/PageHeading";
import { library } from '@fortawesome/fontawesome-svg-core'
import { faArrowAltToTop, faArrowAltToBottom, faTh, faBrowser, faCube, faPalette, faCheeseburger, faDraftingCompass, faWindow, faPaperPlane, faPlus, faTrashAlt, faExclamationTriangle } from '@fal'
import { faUserCog } from '@fas'
import { routeType } from '@/types/route'

library.add(faUserCog, faArrowAltToTop, faArrowAltToBottom, faTh, faBrowser, faCube, faPalette, faCheeseburger, faDraftingCompass, faWindow, faExclamationTriangle)

const props = defineProps<{
    title: string,
    pageHead: PageHeadingTypes
    builder: string
    imagesUploadRoute: routeType
    updateRoute: routeType
    snapshot: routeType
    mergeTags: Array<any>
    sendTestRoute: routeType
    storeTemplateRoute: routeType
    deleteTemplateRoute: routeType
    organisationSlug: string
    shopSlug: string
    indexRoute: routeType
}>()

const inProgress = ref(false)


</script>


<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
    </PageHeading>

    <!-- beefree -->
    <Beetree v-if="builder == 'beefree'" :updateRoute="updateRoute" :imagesUploadRoute="imagesUploadRoute"
        :snapshot="snapshot" :mergeTags="mergeTags" :organisationSlug="organisationSlug" @onSave="onSave"
        :shopSlug="shopSlug" @sendTest="openSendTest" @saveTemplate="onSaveTemplate" ref="_beefree" />

</template>
