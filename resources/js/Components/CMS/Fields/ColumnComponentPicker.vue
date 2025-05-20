<script setup lang="ts">
import { ref, onMounted, provide } from 'vue'
import { trans } from "laravel-vue-i18n"
import BlockList from '@/Components/CMS/Webpage/BlockList.vue'
import Modal from "@/Components/Utils/Modal.vue"
import { notify } from '@kyvg/vue3-notification'
import SideEditor from '@/Components/Workshop/SideEditor/SideEditor.vue'
import Image from '@/Components/Image.vue'
import axios from 'axios'
import { routeType } from "@/types/route";
import { getBlueprint } from '@/Composables/getBlueprintWorkshop'

const props = defineProps<{ uploadRoutes: routeType }>();
const model = defineModel()
const emit = defineEmits(['update:modelValue'])

const modelModalBlocklist = ref(false)
const webBlockTypes = ref<Array<any>>([])

const onPickBlock = (block: any) => {
	modelModalBlocklist.value = false
	emit('update:modelValue', block)
}

const getWebBlockTypes = async () => {
	try {
		const { data } = await axios.get(route('grp.json.web-block-types.index'))
		webBlockTypes.value = data
	} catch (error) {
		notify({
			title: trans("Something went wrong"),
			text: trans("Failed to load block types"),
			type: "error"
		})
	}
}


/* const onSaveWorkshopFromId = (blockId: number, from?: string) => {
  emit("update:modelValue", model.value);
};

provide("onSaveWorkshopFromId", onSaveWorkshopFromId); */


onMounted(() => {
	getWebBlockTypes()
})
</script>

<template>
	<div class="group hover:bg-gray-100 relative border border-gray-400 border-dashed overflow-hidden rounded-md text-center cursor-pointer"
		@click="modelModalBlocklist = true">
		<div v-if="!model" class="text-sm">
			<div class="py-3">
				<p>{{ trans("Pick Block") }}</p>
			</div>
		</div>

		<div v-else class="h-32 relative flex justify-center items-center">
			<Image :src="model?.screenshot" class="w-auto h-fit" />
			<div
				class="absolute hover:bg-black/50 z-10 inset-0 flex items-center justify-center text-white text-sm opacity-0 group-hover:opacity-100">
				{{ trans("Pick Block") }}
			</div>
			
		</div>
	</div>


	<div v-if="model" class="w-full mt-2">
		<SideEditor 
			v-model="model.data.fieldValue" 
			:blueprint="getBlueprint(model.code)" 
			@update:modelValue="(e) =>{emit('update:modelValue', e)}"
			:uploadImageRoute="uploadRoutes" 
		/>
	</div>
		
	<Modal :isOpen="modelModalBlocklist" @onClose="modelModalBlocklist = false">
		<BlockList :onPickBlock="onPickBlock" :webBlockTypes="webBlockTypes" scope="element" />
	</Modal>
</template>

<style scoped>
</style>
