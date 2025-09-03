<script setup lang="ts">
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { ref, computed, watch } from "vue"
import { faTrash as falTrash, faEdit, faExternalLink, faPuzzlePiece, faShieldAlt, faInfoCircle, faChevronDown, faChevronUp, faBox, faVideo } from "@fal"
import { faCircle, faPlay, faTrash, faPlus, faBarcode } from "@fas"
import { trans } from "laravel-vue-i18n"
import { Link } from "@inertiajs/vue3"
import EditTradeUnit from "@/Components/Goods/EditTradeUnit.vue"
import { Fieldset, Select } from "primevue"
import { routeType } from "@/types/route"


library.add(faCircle, faTrash, falTrash, faEdit, faExternalLink, faPlay, faPlus, faBarcode, faPuzzlePiece, faShieldAlt, faInfoCircle, faChevronDown, faChevronUp, faBox, faVideo)

const props = defineProps<{
    form: any
    fieldName: string
    options?: any
    fieldData?: {
        type: string
        trade_units : {
			brand: {}
			brand_routes: {
				index_brand: routeType
				store_brand: routeType
				update_brand: routeType
				delete_brand: routeType
				attach_brand: routeType
				detach_brand: routeType
			}
			tag_routes: {
				index_tag: routeType
				store_tag: routeType
				update_tag: routeType
				delete_tag: routeType
				attach_tag: routeType
				detach_tag: routeType
			}
			tags: {}[]
			tags_selected_id: number[]
		}[]
    }
}>()



console.log("tradeunit.vue", props)
const selectedTradeUnit = ref(props.fieldData.trade_units.length > 0 ? props.fieldData.trade_units[0].tradeUnit.code : null)
const compSelectedTradeUnit = computed(() => {
    return props.fieldData.trade_units.find((unit) => unit.tradeUnit.code === selectedTradeUnit.value)
})

</script>

<template>
    <div>
        <Fieldset class="bg-white rounded-xl shadow-sm w-full md:w-auto" legend="Trade units">
            <template #legend>
                <div class="flex items-center gap-2 font-bold">
                    <FontAwesomeIcon icon="fal fa-atom" class="text-gray-400" fixed-width />
                    Trade units
                </div>
            </template>

            <template #default>
                <div>
                    <template v-if="props.fieldData.trade_units.length">
                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 mb-4">
                            <Select v-model="selectedTradeUnit" :options="props.fieldData.trade_units"
                                optionLabel="tradeUnit.name" optionValue="tradeUnit.code" placeholder="Select a City"
                                class="w-full sm:w-80" />
                            <Link v-if="compSelectedTradeUnit?.tradeUnit?.slug"
                                :href="route('grp.goods.trade-units.show', compSelectedTradeUnit?.tradeUnit.slug)"
                                v-tooltip="trans('Open trade unit')"
                                class="text-gray-400 hover:text-gray-600 text-center sm:text-left">
                            <FontAwesomeIcon icon="fal fa-external-link" fixed-width />
                            </Link>
                        </div>
                        <EditTradeUnit v-if="compSelectedTradeUnit" v-bind="compSelectedTradeUnit" />
                    </template>
                    <div v-else class="text-gray-500 text-center py-4">
                        {{ trans("No trade units for this product") }}
                    </div>
                </div>
            </template>
        </Fieldset>
    </div>
</template>

<style scoped></style>