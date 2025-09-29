<script setup lang="ts">
import { faCheckCircle } from "@fas"
import { faTimes } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { router, useForm } from "@inertiajs/vue3"
import BrandsTradeUnit from "@/Components/Forms/Fields/BrandsTradeUnit.vue"
import TagsTradeUnits from "@/Components/Forms/Fields/TagsTradeUnits.vue"
import Button from "./Elements/Buttons/Button.vue"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"
import axios from "axios"

library.add(faCheckCircle, faTimes)

const props = withDefaults(defineProps<{
    data: {
        id: number
        tags?: { id: number; name: string }[]
        brands?: { id: number; name: string }[]
    }
}>(), {})

// ✅ Emit untuk cancel & save success
const emit = defineEmits<{
    (e: "cancel"): void
    (e: "saveSuccess", payload: { tags: number[]; brands: number[] }): void
}>()

// ✅ Initialize inertia form dari props
const form = useForm({
    tags: props.data?.tags?.map((item) => item.id) || [],
    brands: props.data?.brands ? props.data?.brands[0].id : null,
})

const submit = async () => {
  try {
    const response = await axios.patch(
      route("grp.models.trade-unit.update", { tradeUnit: props.data.id }),
      form.data()
    )

    console.log(response)
    notify({
      title: trans("Success"),
      text: trans("Data has been updated successfully"),
      type: "success",
    })

    // Kirim balik id + full data (bisa juga ambil dari response)
    emit("saveSuccess", {
      id: props.data.id,
      ...response.data, // optional kalau API return data lengkap
    })
  } catch (error: any) {
    // Bisa dapat pesan error dari response
    const message =
      error.response?.data?.message ||
      Object.values(error.response?.data?.errors || {})[0] ||
      trans("Please try again")

    notify({
      title: trans("Something went wrong"),
      text: message,
      type: "error",
    })
  }
}


// ✅ Tag routes
const tagRoutes = {
    index_tag: {
        name: "grp.json.trade_units.tags.index",
        parameters: { tradeUnit: props.data.id },
    },
    store_tag: {
        name: "grp.models.trade-unit.tags.store",
        parameters: { tradeUnit: props.data.id },
    },
    update_tag: {
        name: "grp.models.trade-unit.tags.update",
        parameters: { tradeUnit: props.data.id },
        method: "patch",
    },
    delete_tag: {
        name: "grp.models.trade-unit.tags.delete",
        parameters: { tradeUnit: props.data.id },
        method: "delete",
    },
    attach_tag: {
        name: "grp.models.trade-unit.tags.attach",
        parameters: { tradeUnit: props.data.id },
        method: "post",
    },
    detach_tag: {
        name: "grp.models.trade-unit.tags.detach",
        parameters: { tradeUnit: props.data.id },
        method: "delete",
    },
}

// ✅ Brand routes
const brandRoutes = {
    index_brand: {
        name: "grp.json.brands.index",
        parameters: {},
    },
    store_brand: {
        name: "grp.models.trade-unit.brands.store",
        parameters: { tradeUnit: props.data.id },
    },
    update_brand: {
        name: "grp.models.trade-unit.brands.update",
        parameters: { tradeUnit: props.data.id },
        method: "patch",
    },
    delete_brand: {
        name: "grp.models.trade-unit.brands.delete",
        parameters: { tradeUnit: props.data.id },
        method: "delete",
    },
    attach_brand: {
        name: "grp.models.trade-unit.brands.attach",
        parameters: { tradeUnit: props.data.id },
        method: "post",
    },
    detach_brand: {
        name: "grp.models.trade-unit.brands.detach",
        parameters: { tradeUnit: props.data.id },
        method: "delete",
    },
}
</script>

<template>
    <form  class="space-y-6 bg-white rounded-xl shadow-sm">
        <!-- ✅ Tags field -->
        <div>
            <label for="tags" class="block text-sm font-semibold text-gray-800">
                Tags
            </label>
            <TagsTradeUnits id="tags" :form="form" field-name="tags" :fieldData="{ tag_routes: tagRoutes }" />
        </div>

        <!-- ✅ Brands field -->
        <div>
            <label for="brands" class="block text-sm font-semibold text-gray-800">
                Brands
            </label>
            <BrandsTradeUnit id="brands" :form="form" field-name="brands"
                :fieldData="{ brand_routes: brandRoutes, labelProp: 'name', valuePorp: 'id' }" />
        </div>

        <!-- Divider -->
        <div class="border-t border-gray-200"></div>

        <!-- Actions -->
        <div class="flex justify-end gap-3">
            <Button type="negative"
                class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-100 transition"
                label="Cancel" @click="emit('cancel')" />
            <Button type="save" :disabled="form.processing" @click="submit"
                class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50 transition"
                label="Save" />
        </div>
    </form>
</template>
