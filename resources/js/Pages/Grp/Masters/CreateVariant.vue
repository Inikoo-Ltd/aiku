<script setup lang="ts">
import { computed } from "vue"
import { Head, useForm, } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading"
import Button from "@/Components/Elements/Buttons/Button.vue"
import PureMultiselectInfiniteScroll from "@/Components/Pure/PureMultiselectInfiniteScroll.vue"
import { trans } from "laravel-vue-i18n"
import { routeType } from "@/types/route"
import PureVariantField from "@/Components/Pure/PureVariantField.vue"

type Variant = {
    label: string
    options: string[]
    active?: boolean
}

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    master_asset: {}
    master_assets_route: routeType
    save_route: routeType
}>()



const form = useForm({
    product_leader: null as any,
    data_variants: {
        variants: [
            { label: "Color", options: ["Red", "Blue"], active: false },
            { label: "Size", options: ["L", "XL"], active: false }
        ] as Variant[],
        groupBy: "Color",
        products: {} as Record<string, any>
    }
})


const isValid = computed(() => {
    if (!form.product_leader) return true
    if (!form.data_variants.variants.length) return true
    return false
})


const save = () => {
    if (isValid.value) return
    form.post(route(props.save_route.name, props.save_route.parameters))
}

</script>


<template>
    <Head :title="capitalize(props.title)" />
    <PageHeading :data="props.pageHead" />

    <div class="flex justify-center mt-6">
        <div class="w-full max-w-2xl p-4 bg-white rounded-lg shadow space-y-4">

            <!-- Product Leader -->
            <div>
                <label class="text-xs font-medium">
                    Product Leader <span class="text-red-500">*</span>
                </label>
                <PureMultiselectInfiniteScroll v-model="form.product_leader" :fetchRoute="props.master_assets_route"
                    :required="true" valueProp="id" label-prop="name" :caret="false"
                    :placeholder="trans('Select Product Leader')" />
            </div>

            <!-- Variants -->
            <div>
                <PureVariantField v-model="form.data_variants" :master_assets_route="master_assets_route"
                    :master_asset="master_asset" />
            </div>

            <!-- SAVE -->
            <Button full class="bg-blue-600 text-white disabled:opacity-50" :loading="form.processing" @click="save" :disabled="isValid" :label="'Save Variants'"></Button>
        </div>
    </div>
</template>

<style scoped>
:deep(.multiselect-wrapper) {
    justify-content: space-between;
}
</style>
