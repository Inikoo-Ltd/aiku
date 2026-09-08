<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3";
import { ref } from "vue";
import { trans } from "laravel-vue-i18n";
import { capitalize } from "@/Composables/capitalize";
import { PageHeadingTypes } from "@/types/PageHeading";
import { routeType } from "@/types/route";
import { library } from "@fortawesome/fontawesome-svg-core";
import { faSyncAlt, faPlus } from "@fas";
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
import Button from "@/Components/Elements/Buttons/Button.vue";
import Modal from "@/Components/Utils/Modal.vue";
import AddBundles from "@/Components/Dropshipping/AddBundles.vue";

library.add(faFileExcel, faBracketsCurly, faImage, faSyncAlt, faPlus, faBox, faArrowLeft, faArrowRight, faUpload);

defineProps<{
    title: string
    pageHead: PageHeadingTypes
    data : {}
    create_bundle?: {
        customer_id: number
        shop_data: {
            currency_code: string
            currency_symbol: string
        }
        routes: {
            itemRoute: routeType
        }
        bundle_routes: {}
    }
}>();

const isOpenModalCreateBundle = ref(false)
const step = ref({ current: 0 })

const onBundleDone = () => {
    isOpenModalCreateBundle.value = false
    step.value = { current: 0 }
    router.reload()
}

</script>

<template>
    <div>
        <Head :title="capitalize(title)" />
        <div v-if="create_bundle?.bundle_routes" class="flex justify-end px-4 pt-2">
            <Button
                @click="() => (isOpenModalCreateBundle = true)"
                :label="trans('Create bundle')"
                icon="fas fa-plus" />
        </div>
        <RetinaTableProducts :data="data"/>

        <Modal
            v-if="create_bundle?.bundle_routes"
            :isOpen="isOpenModalCreateBundle"
            @onClose="isOpenModalCreateBundle = false"
            :isClosableInBackground="step.current !== 1"
            width="w-full max-w-7xl max-h-[600px] md:max-h-[85vh] overflow-y-auto">
            <AddBundles
                :step="step"
                :customer_id="create_bundle.customer_id"
                :routes="create_bundle.routes"
                :bundle_routes="create_bundle.bundle_routes"
                :shop_data="create_bundle.shop_data"
                @onClose="isOpenModalCreateBundle = false"
                @onDone="onBundleDone" />
        </Modal>
    </div>
</template>
