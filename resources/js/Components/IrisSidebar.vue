<script setup lang="ts">
import Drawer from 'primevue/drawer';
import { ref, inject, onMounted, onUnmounted, computed, watch, type Ref } from 'vue';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { faBars } from '@fal';
import { getStyles } from '@/Composables/styles';
import { isNull } from 'lodash-es';
import IrisSidebarDesktop from './Iris/Layout/IrisSidebarDesktop.vue';
import IrisSidebarMobile from './Iris/Layout/IrisSidebarMobile.vue';
import { Image as ImageTS } from '@/types/Image';
import { trans } from 'laravel-vue-i18n';
import { faSearch, faTimes } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import axios from 'axios'
library.add(faSearch, faTimes)

const props = defineProps<{
	header: { logo?: { image: { source: string } } }
	screenType: string
	productCategories: Array<any>
	menu?: { data: Array<any> }
	sidebarLogo: ImageTS
	sidebar?: {
		data: {
			fieldValue: {
				sidebar_logo: ImageTS
				logo_dimension: {
					width: {
						unit: string
						value: number
					}
					height: {
						unit: string
						value: number
					}
				}
				container: {}
				navigation: {}
				navigation_bottom: {}
				product_categories: {}
			}
		}
	}
}>()


const irisLayout = inject("layout", {})
const sidebarMenu = inject<Ref<any> | null>('sidebarMenu', null)

const isOpenMenuMobile = inject("isOpenMenuMobile", ref(false))

const isMobile = ref(false)
const activeIndex = ref<number | null>(null) // active category
const activeSubIndex = ref<number | null>(null) // active subdepartment
const activeCustomIndex = ref<number | null>(null) // active custom menu
const activeCustomSubIndex = ref<number | null>(null) // active custom menu subdepartment
const activeCustomTopIndex = ref<number | null>(null) // active custom menu top
const activeCustomTopSubIndex = ref<number | null>(null) // active custom menu top subdepartment


const sortedProductCategories = computed(() => {
	if (!props.productCategories) return []
	return [...props.productCategories].sort((a, b) =>
		(a.name || "").localeCompare(b.name || "", undefined, { sensitivity: "base" })
	)
})



const convertToDepartmentStructure = (menusData: any[]): any[] => {
	const dataArray = Array.isArray(menusData) ? menusData : [menusData]

	const removeProtocol = (url: string | null | undefined): string | null => {
		if (!url || typeof url !== 'string') return null
		return url.replace(/^https?:\/\//, '')
	}

	return dataArray.map(menu => {
		const departmentStructure: any = {
			url: removeProtocol(menu?.link?.href),
			name: menu?.label || null,
			type: menu?.link?.type || null,
			sub_departments: [] as any[]
		}

		if (Array.isArray(menu?.subnavs)) {
			menu.subnavs.forEach((subnav: any) => {
				const subDepartment: any = {
					url: removeProtocol(subnav?.link?.href),
					name: subnav?.title || null,
					type: subnav?.link?.type || null,
					families: [] as any[]
				}

				if (Array.isArray(subnav?.links)) {
					subnav.links.forEach((link: any) => {
						subDepartment.families.push({
							url: removeProtocol(link?.link?.href),
							name: link?.label || null,
							type: link?.link?.type || null
						})
					})
				}

				departmentStructure.sub_departments.push(subDepartment)
			})
		}

		return departmentStructure
	})
}

const internalCustomMenusBottom = ref<any[]>([])
const internalCustomMenusTop = ref<any[]>([])

const computedSidebarSource = computed(() => sidebarMenu?.value || irisLayout.iris?.sidebar)

watch(
	computedSidebarSource,
	(newValue) => {
		if (newValue) {
			const navigationBottomData = newValue?.data?.fieldValue?.navigation_bottom?.filter((item: any) => !item.hidden) ?? []
			const navigationData = newValue?.data?.fieldValue?.navigation?.filter((item: any) => !item.hidden) ?? []

			internalCustomMenusBottom.value = navigationBottomData.length ? convertToDepartmentStructure(navigationBottomData) : []
			internalCustomMenusTop.value = navigationData.length ? convertToDepartmentStructure(navigationData) : []
		} else {
			internalCustomMenusBottom.value = []
			internalCustomMenusTop.value = []
		}
	},
	{ immediate: true, deep: true }
)

const customMenusBottom = computed(() => internalCustomMenusBottom.value)
const customMenusTop = computed(() => internalCustomMenusTop.value)


console.log('wwwwww', irisLayout.iris.sidebar)
const sidebarFieldValue = computed(() =>
    irisLayout.iris.sidebar?.data?.fieldValue ?? props.sidebar?.data?.fieldValue
)


// const sortedNavigation = computed(() => {
//     if (!props.menu?.navigation) return [];
//     return [...props.menu.navigation].sort((a, b) =>
//         (a.label || '').localeCompare(b.label || '', undefined, { sensitivity: 'base' })
//     );
// });

const sortedSubDepartments = computed(() => {
	const category = sortedProductCategories.value?.[activeIndex.value]
	if (!category) return []

	return [...(category.sub_departments ?? []), ...(category.collections ?? [])]
		.filter((item) => item?.name)
		.sort((a, b) => a.name.localeCompare(b.name))
});

// Custom sub departments without sorting
const customSubDepartments = computed(() => {
	if (
		activeCustomIndex.value === null ||
		!customMenusBottom.value[activeCustomIndex.value]?.sub_departments
	)
		return []
	return customMenusBottom.value[activeCustomIndex.value].sub_departments
});

const sortedFamilies = computed(() => {
	if (
		activeSubIndex.value === null ||
		!sortedSubDepartments.value[activeSubIndex.value]?.families
	)
		return []
	return [...sortedSubDepartments.value[activeSubIndex.value].families].sort((a, b) =>
		(a.name || "").localeCompare(b.name || "", undefined, { sensitivity: "base" })
	)
});

// Custom families without sorting
const customFamilies = computed(() => {
	if (
		activeCustomSubIndex.value === null ||
		!customSubDepartments.value[activeCustomSubIndex.value]?.families
	)
		return []
	return customSubDepartments.value[activeCustomSubIndex.value].families
});

// Custom top sub departments without sorting
const customTopSubDepartments = computed(() => {
	if (
		activeCustomTopIndex.value === null ||
		!customMenusTop.value[activeCustomTopIndex.value]?.sub_departments
	)
		return []
	return customMenusTop.value[activeCustomTopIndex.value].sub_departments
});

// Custom top families without sorting
const customTopFamilies = computed(() => {
	if (
		activeCustomTopSubIndex.value === null ||
		!customTopSubDepartments.value[activeCustomTopSubIndex.value]?.families
	)
		return []
	return customTopSubDepartments.value[activeCustomTopSubIndex.value].families
});

// reset subdepartment when category changes
const setActiveCategory = (index: number) => {
	activeIndex.value = index;
	activeSubIndex.value = null;
	// Reset custom menu states
	activeCustomIndex.value = null;
	activeCustomSubIndex.value = null;
	activeCustomTopIndex.value = null;
	activeCustomTopSubIndex.value = null;
};

const setActiveCustomCategory = (index: number) => {
	activeCustomIndex.value = index;
	activeCustomSubIndex.value = null;
	// Reset product category states
	activeIndex.value = null;
	activeSubIndex.value = null;
	// Reset custom top menu states
	activeCustomTopIndex.value = null;
	activeCustomTopSubIndex.value = null;
};

const setActiveCustomTopCategory = (index: number) => {
	activeCustomTopIndex.value = index;
	activeCustomTopSubIndex.value = null;
	// Reset product category states
	activeIndex.value = null;
	activeSubIndex.value = null;
	// Reset custom bottom menu states
	activeCustomIndex.value = null;
	activeCustomSubIndex.value = null;
};

const checkMobile = () => {
	isMobile.value = window.innerWidth < 768
};

onMounted(() => {
    checkMobile()
    window.addEventListener("resize", checkMobile)
})
onUnmounted(() => {
	window.removeEventListener("resize", checkMobile)
});

// const getHref = (item) => {
// 	if (item.type === 'external' && item.url !== null) {
// 		if (item.url.startsWith('http://') || item.url.startsWith('https://')) {
// 			return item.url;
// 		}
// 		return `https://${item.url}`;
// 	}

// 	return `${item.url}` // Internal
// }

const getTarget = (item) => {
	if (item.target) {
		return item.target
	}
	if (item.type === 'external') {
		return '_blank'
	}
	return '_self'
};

const internalHref = (item) => {
	// "https://www.aw-dropship.com/new",   -> /new
	// "http://aw-dropship.com/new",   -> /new
	// "www.aw-dropship.com/new",   -> /new
	// "aw-dropship.com/new"   -> /new
	if (!item.url) return ""

	const path = item.url.includes("/") ? item.url.replace(/^(https?:\/\/)?(www\.)?[^/]+/, "") : item.url

	return path
}

const onClickLuigi = () => {
	const input = document.getElementById('luigi_mobile') as HTMLInputElement | null;
	if (input) input.focus();
}


// Section: Fetch Sidebar
interface DataJsonSidebar {
	sidebar: {
		data: {
			fieldValue: {
				additional_items: {
					items_list: {
						icon: string[]
						text: string
						ulid: string
						url: {
							href: string
							type: string
							target: string
						}
					}[]
				}
				container: {}
				sidebar_logo: ImageTS
				logo_dimension: {
					width: {
						unit: string
						value: number
					}
					height: {
						unit: string
						value: number
					}
				}
				navigation: {
					icon: string[]
					id: string
					label: string
					link: {
						href: string
						type: string
						target: string
					}
					type: string
				}
				navigation_bottom: {
					id: string
					label: string
					link: {
						href: string
						type: string
						target: string
					}
					type: string
				}
				product_categories: {
					collections: {
						id: string
						name: string
						url: string
						families: {
							id: string
							name: string
							url: string
						}[]
					}[]
					name: string
					sub_departments: {
						name: string
						url: string
						collections: {}[]
						families: {
							name: string
							url: string
						}[]
					}
					url: string
				}
			}
		}
		product_categories: {
			collections: {
				id: string
				name: string
				url: string
				families: {
					id: string
					name: string
					url: string
				}[]
			}[]
			sub_departments: {
				name: string
				url: string
				collections: {}[]
				families: {
					name: string
					url: string
				}[]
			}[]
			name: string
			url: string
		}[]
	}
}
const layout = inject('layout', retinaLayoutStructure)
const isSidebarFetching = ref(false)
const fetchSidebarOnce = async () => {
	// To take custom bottom navigation
    if (layout.iris.isSidebarLoaded || isSidebarFetching.value) return

    isSidebarFetching.value = true

    try {
		layout.iris.isSidebarLoading = true
		const baseUrl = window.location.origin
        const { data } = await axios.get(`${baseUrl}/json/sidebar`) as { data: DataJsonSidebar }
        // const { data } = await axios.get(route("iris.json.sidebar")) as { data: DataJsonSidebar }
        
        console.log('ddddddata', data)

        layout.iris.sidebar  = data.sidebar

		layout.iris.isSidebarLoading = false
        layout.iris.isSidebarLoaded = true
    } catch (e) {
        console.error("[IrisSidebar] fetch failed", e)
    } finally {
        isSidebarFetching.value = false
    }
}
</script>

<template>
	<div class="mobile-menu editor-class">

		<!-- Button: hamburger (showed on mobile) -->
		<button @click="isOpenMenuMobile = true" class="">
			<slot name="icon">
				<FontAwesomeIcon
					:icon="props.header?.mobile?.menu?.icon || faBars"
					:style="{ ...getStyles(header?.mobile?.menu?.container?.properties, screenType) }"
					fixed-width
					aria-hidden="true" />
			</slot>
		</button>

		<Drawer
			v-model:visible="isOpenMenuMobile"
			:header="''"
			:showCloseIcon="false"
			:style="{
				...getStyles(irisLayout?.app?.webpage_layout?.container?.properties, screenType),
				margin: 0,
				padding: 0,
				border: 'none !important',
				...getStyles(props.menu?.container?.properties),
				...getStyles(props.sidebar?.data?.fieldValue?.container?.properties),
				width: isMobile
					? null
					: !isNull(activeIndex) ||
					  !isNull(activeCustomIndex) ||
					  !isNull(activeCustomTopIndex)
					? !isNull(activeSubIndex) ||
					  !isNull(activeCustomSubIndex) ||
					  !isNull(activeCustomTopSubIndex)
						? '798px'
						: '545px'
					: '290px',
			}"
			class="h-screen"
			@show="() => fetchSidebarOnce()"
		>
			<template #header>
				<div>
					<div class="md:max-w-[270px] overflow-hidden">
						<!-- <Image
                            v-if="sidebarLogo"
                            :src="sidebarLogo"
                            class="h-fit w-full object-contain aspect-auto"
                            :alt="trans('Sidebar logo')"
                        /> -->
						<img
							xv-else
							:src="sidebarLogo?.original || header?.logo?.image?.source?.original"
							:alt="header?.logo?.alt"
							zclass="w-full h-auto max-h-20 object-contain"
							:style="getStyles(props.sidebar?.data?.fieldValue?.logo_dimension)" />
					</div>

					<!-- Section: input search -->
					<div class="md:hidden mt-6 flex gap-x-4 items-center">
						<div
							@click="() => onClickLuigi()"
							class="flex-grow border border-gray-300/40 rounded-md px-2 py-1">
							<FontAwesomeIcon
								icon="fal fa-search"
								class=""
								fixed-width
								aria-hidden="true" />
							<span v-if="irisLayout?.currentQuery?.q" class="ml-2 text-sm">{{
								irisLayout?.currentQuery?.q
							}}</span>
							<span v-else class="ml-2 text-sm italic opacity-60">{{
								trans("I am looking for..")
							}}</span>
						</div>
					</div>
					<span class="cursor-pointer absolute -right-12 top-12 opacityx-70 text-xl text-white pointer-events-none">
						<FontAwesomeIcon
							icon="fal fa-times"
							fixed-width
							aria-hidden="true" />
					</span>
				</div>
			</template>

			<!-- Sidebar Menu: Mobile -->
			<IrisSidebarMobile
				v-if="isMobile"
				:containerStyle="
					props.sidebar?.data?.fieldValue?.container?.properties ||
					props.menu?.container?.properties
				"
				:productCategories
				:customMenusTop
				:customTopSubDepartments
				:customMenusBottom
				:customSubDepartments
				:activeIndex
				:activeCustomIndex
				:activeCustomTopIndex
				:internalHref
				:getTarget
				:setActiveCategory
				:setActiveCustomCategory
				:setActiveCustomTopCategory
				:sortedFamilies
				:customFamilies
				:customTopFamilies
				:sortedProductCategories
				:sortedSubDepartments
				:activeSubIndex
				:activeCustomSubIndex
				:activeCustomTopSubIndex
				:changeActiveSubIndex="(index) => (activeSubIndex = index)"
				:changeActiveCustomSubIndex="(index) => (activeCustomSubIndex = index)"
				:changeActiveCustomTopSubIndex="(index) => (activeCustomTopSubIndex = index)"
				@closeMobileMenu="isOpenMenuMobile = false"
				:fieldValue="sidebarFieldValue" />

			<!-- Sidebar Menu: Desktop -->
			<IrisSidebarDesktop
				v-else
				:containerStyle="
					props.sidebar?.data?.fieldValue?.container?.properties ||
					props.menu?.container?.properties
				"
				:productCategories
				:customMenusTop
				:customTopSubDepartments
				:customMenusBottom
				:customSubDepartments
				:activeIndex
				:activeCustomIndex
				:activeCustomTopIndex
				:internalHref
				:getTarget
				:setActiveCategory
				:setActiveCustomCategory
				:setActiveCustomTopCategory
				:sortedFamilies
				:customFamilies
				:customTopFamilies
				:sortedProductCategories
				:sortedSubDepartments
				:activeSubIndex
				:activeCustomSubIndex
				:activeCustomTopSubIndex
				:changeActiveSubIndex="(index) => (activeSubIndex = index)"
				:changeActiveCustomSubIndex="(index) => (activeCustomSubIndex = index)"
				:changeActiveCustomTopSubIndex="(index) => (activeCustomTopSubIndex = index)"
				@closeMobileMenu="isOpenMenuMobile = false"
				:fieldValue="sidebarFieldValue" />
		</Drawer>
	</div>
</template>

<style scoped lang="scss">
/* ✅ Smooth width transition */
// .p-drawer {
//     transition: width 0.35s ease-in-out;
//     background: #fff;
// }

// .p-drawer-content {
//     padding: 0 !important;
//     transition: width 0.35s ease-in-out;
// }

/* Hover & active states */
// .menu-link {
//     @apply flex items-center justify-between px-4 py-2 cursor-pointer rounded-lg;
// }

// .menu-link:hover {
//     background: #f9fafb;
// }

// .menu-link.active {
//     background: #f3f4f6;
//     font-weight: 600;
//     color: #2563eb;
// }
</style>
