<script setup lang="ts">
import { ref, onMounted, inject, watch } from 'vue'
import PureInput from '@/Components/Pure/PureInput.vue';
import { trans } from 'laravel-vue-i18n';
import Button from '@/Components/Elements/Buttons/Button.vue';
import RadioButton from "primevue/radiobutton"
import { set } from 'lodash-es'
import SelectQuery from "@/Components/SelectQuery.vue"

import { library } from '@fortawesome/fontawesome-svg-core';
import { faChevronRight, faSignOutAlt, faShoppingCart, faSearch, faChevronDown, faTimes, faPlusCircle, faBars, faTrashAlt } from '@fas';
import { faHeart } from '@far';

library.add(faChevronRight, faSignOutAlt, faShoppingCart, faHeart, faSearch, faChevronDown, faTimes, faPlusCircle, faBars, faTrashAlt);

const props = defineProps<{
    modelValue: Object,
}>()

const emits = defineEmits<{
    (e: 'onSave', value: string): void
}>()


function getRoute() {
	if (route().current().includes('fulfilments')) {
		return route('grp.org.fulfilments.show.web.webpages.index', {
			organisation: route().params['organisation'],
			fulfilment: route().params['fulfilment'],
			website: route().params['website'],
		})

	} else if (route().current().includes('shop')) {
		return route('grp.org.shops.show.web.webpages.index', {
			organisation: route().params['organisation'],
			shop: route().params['shop'],
			website: route().params['website'],
		})
	}else {
        return route('grp.org.shops.show.web.webpages.index', {
			organisation: route().params['organisation'],
			shop: route().params['shop'],
			website: route().params['website'],
		})
    }
}

const localModel = ref({
    label : props.modelValue.label || null,
	data : props.modelValue.link || {},
    link : props.modelValue.link ? props.modelValue.link : {
        type: 'external',
        href: null,
        workshop : null,
        id : null,
        target : "_self",
    },
});

const options = ref([
	{ label: "Internal", value: "internal" },
	{ label: "External", value: "external" },
])

const targets = ref([
	{ label: "In this Page", value: "_self" },
	{ label: "New Page", value: "_blank" },
])

const selectQueryOnChange = (e) => {
    localModel.value.link = {
        ...localModel.value.link,
        href: e.href,
        workshop : e.workshop,
        id : e.id,
    }
}


</script>

<template>
    <div>
        <div v-if="localModel?.link?.target">
			<div  class="text-gray-500 text-xs tracking-wide mb-2">{{ trans("Target") }}</div>
			<div class="mb-3 border border-gray-300 rounded-md w-full px-4 py-2">
				<div class="flex flex-wrap justify-between w-full">
					<div v-for="(option, indexOption) in targets" class="flex items-center gap-2">
						<RadioButton
							:modelValue="localModel.link.target"
							@update:modelValue="(e: string) => set(localModel,['link','target'], e)"
							:inputId="`${option.value}${indexOption}`"
							name="target"
							size="small"
							:value="option.value"
						/>
						<label @click="() => localModel.link.target = option.value" :for="`${option.value}${indexOption}`" class="cursor-pointer">{{ option.label }}</label>
					</div>
				</div>
			</div>
		</div>

		<div v-if="localModel?.link?.type">
			<div class="text-gray-500 text-xs tracking-wide mb-2">{{ trans("Type") }}</div>
			<div class="mb-3 border border-gray-300 rounded-md w-full px-4 py-2">
				<div class="flex flex-wrap justify-between w-full">
					<div v-for="(option, indexOption) in options" class="flex items-center gap-2">
						<RadioButton
							:modelValue="localModel.link.type"
							@update:modelValue="(e: string) => set(localModel,['link','type'], e)"
							:inputId="`${option.value}${indexOption}`"
							name="type"
							size="small"
							:value="option.value"
						/>
						<label @click="() => localModel.link.type = option.value" :for="`${option.value}${indexOption}`" class="cursor-pointer">{{ option.label }}</label>
					</div>
				</div>
			</div>
		</div>
        <div>
            <div class="my-2 text-gray-500 text-xs tracking-wide mb-2">{{ trans("Label") }}</div>
            <PureInput v-model="localModel.label" />
        </div>

        <div>
			<div class="my-2 text-gray-500 text-xs tracking-wide mb-2">{{ trans("Destination") }}</div>
			<PureInput
				v-if="localModel?.link?.type == 'external'"
				v-model="localModel.link.href"
			/>
			
			<SelectQuery
				v-if="localModel?.link?.type == 'internal'"
				:object="true"
				fieldName="data"
                :onChange="selectQueryOnChange"
				:value="localModel"
				:closeOnSelect="true"
				label="href" 
				:urlRoute="getRoute()"
				/>
		</div>
      
        <div class="flex justify-end mt-3">
            <Button type="save" @click="()=>emits('onSave',localModel)" />
        </div>

    </div>

</template>
<style scss scoped>
</style>
