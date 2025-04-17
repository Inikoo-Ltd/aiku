<script setup lang="ts">
import PureTimeline from '@/Components/Pure/PureTimeline.vue'

import beePluginJsonExample from "@/Components/CMS/Website/Outboxes/Unlayer/beePluginJsonExample"
import { PageHeading as PageHeadingTypes } from '@/types/PageHeading'
import { Tabs as TSTabs } from '@/types/Tabs'
import { routeType } from '@/types/route'

import { library } from "@fortawesome/fontawesome-svg-core";
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { faUser, faEnvelope, faSeedling, faShare, faInboxOut, faCheck, faEnvelopeOpen, faHandPointer, faUserSlash, faPaperPlane, faEyeSlash, faSkull, faDungeon } from '@fal';
library.add(faInboxOut, faEnvelope, faSeedling, faShare, faCheck)

const props = defineProps<{
    data : Object
}>()
console.log('dfdf',props)
</script>



<template>
    <div class="card p-4">

            <!-- Content Section -->
            <div class="md:col-span-8">
                <div
                    class="p-6 border rounded-lg bg-white shadow-lg hover:shadow-2xl transition-transform duration-300">
                    <div v-html="data.layout" />
                </div>
            </div>
    </div>

</template>



<style lang="scss" scoped>
.card {
    padding: 1rem;
    border-radius: 8px;

    @media (max-width: 768px) {
        padding: 0.5rem;
    }
}

</style>
