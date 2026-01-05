<script setup lang="ts">
import { computed } from "vue"
import { Head, useForm, } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { trans } from "laravel-vue-i18n"
import { routeType } from "@/types/route"
import PureVariantField from "@/Components/Pure/PureVariantField.vue"
import { notify } from "@kyvg/vue3-notification"

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
        variants: [] as Variant[],
        groupBy: null,
        products: {} as Record<string, any>
    }
})


const isValid = computed(() => {
    if (!form.data_variants.variants.length || !Object.values(form.data_variants.products).some(p => p.is_leader) || !form.data_variants.groupBy) return true
    return false
})


const save = () => {
    const products = Object.values(form.data_variants.products)

    const leader =
        products.find((item: any) => item.is_leader) ||
        products[0] || 
        null

    form.product_leader = leader ? leader.product.id : null

    console.log(form.data())

    form.post(route(props.save_route.name, props.save_route.parameters), {
        onSuccess: (response) => {
            console.log("Success callback");
        },
        onError: (errorBag) => {
            const errorBagUnique = errorBag ? new Set(Object.values(errorBag).flat()) : [];
            console.log('Fail', errorBag);
            notify({
                title: "Something went wrong",
                data: {
                    html: errorBagUnique ? [...errorBagUnique].join('<br>') : trans("Please try again or contact administrator")
                },
                type: 'error',
                duration: 5000
            });
        },
    });
}

</script>


<template>
    <Head :title="capitalize(props.title)" />
    <PageHeading :data="props.pageHead" />
    <div class="flex justify-center mt-6">
        <div class="w-full max-w-6xl p-4 bg-white rounded-lg shadow space-y-4">
            <div>
                <PureVariantField v-model="form.data_variants" :master_assets_route="master_assets_route" :master_asset="master_asset" />
            </div>
            <Button full class="bg-blue-600 text-white disabled:opacity-50" :loading="form.processing" @click="save" :disabled="isValid" :label="'Save Variants'"></Button>
        </div>
    </div>
</template>

<style scoped>
:deep(.multiselect-wrapper) {
    justify-content: space-between;
}
</style>
