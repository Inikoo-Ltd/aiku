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
    const cleanedVariants = sanitizeVariants()
    const products = Object.values(cleanedVariants.products)

    const leader =
        products.find((item: any) => item.is_leader) ||
        products[0] ||
        null

    form.transform(data => ({
        ...data,
        product_leader: leader ? leader.product.id : null,
        data_variants: cleanedVariants
    }))

    form.post(route(props.save_route.name, props.save_route.parameters), {
        onSuccess: () => {
            notify({
                title: "Success",
                type: "success",
                duration: 3000
            })
        },
        onError: (errorBag) => {
            const messages = errorBag
                ? [...new Set(Object.values(errorBag).flat())].join("<br>")
                : trans("Please try again")

            notify({
                title: "Something went wrong",
                data: { html: messages },
                type: "error",
                duration: 5000
            })
        }
    })
}


const sanitizeVariants = () => {
    const variants = form.data_variants.variants
        .filter(v => v.label?.trim())
        .map(v => ({
            label: v.label.trim(),
            options: v.options.filter(o => o?.trim()),
        }))
        .filter(v => v.options.length > 0)

    const groupBy = variants.some(v => v.label === form.data_variants.groupBy)
        ? form.data_variants.groupBy
        : null

    const validKeys = new Set(
        variants.length
            ? variants.flatMap(v =>
                v.options.map(o => `${v.label}=${o}`)
            )
            : []
    )

    const products: Record<string, any> = {}

    Object.entries(form.data_variants.products).forEach(([id, p]: any) => {
        const keyOnly = Object.entries(p)
            .filter(([k]) => !["product", "is_leader", "all_child_has_webpage"].includes(k))
            .reduce((acc, [k, v]) => ({ ...acc, [k]: v }), {})

        const isValid = Object.entries(keyOnly).every(
            ([k, v]) => validKeys.has(`${k}=${v}`)
        )

        if (!isValid) return

        products[id] = {
            ...keyOnly, 
            product: p.product,
            is_leader: !!p.is_leader,
            all_child_has_webpage: p.all_child_has_webpage,
        }
    })

    return {
        variants,
        groupBy,
        products
    }
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
