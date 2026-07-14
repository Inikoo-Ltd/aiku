import type { Component } from "vue"
import { defineAsyncComponent } from "vue"

import NotFoundComponent from "@/Components/CMS/Webpage/NotFoundComponent.vue"
import ImageIris from '@/Iris/Components/IrisBlocks/ImageIris.vue'
import TextContentIris from "@/Iris/Components/IrisBlocks/TextContentIris.vue"
import WowsbarBannerIris from "@/Iris/Components/IrisBlocks/WowsbarBannerIris.vue"

const async = (loader: () => Promise<any>): Component =>
	defineAsyncComponent({
		loader,
		delay: 200,
		timeout: 15000,
	})

//Department
const DepartmentDescriptionIris = async(() => import("@/Iris/Components/IrisBlocks/DepartmentDescriptionIris.vue"))
const DepartmentDescriptionIris2 = async(() => import("@/Iris/Components/IrisBlocks/DepartmentDescriptionIris2.vue"))

// Sub-department
const SubDepartmentDescriptionIris = async(() => import("@/Iris/Components/IrisBlocks/SubDepartmentDescriptionIris.vue"))

const ListProductsIris = async(() => import("@/Iris/Components/IrisBlocks/Products/ds/ListProductsIris.vue"))
const ProductRender = async(() => import("@/Iris/Components/IrisBlocks/Products/ds/ProductCardDs/ProductCardDs1.vue"))
const ListProductsEcomIris = async(() => import("@/Iris/Components/IrisBlocks/Products/Ecom/ListProductsEcomIris.vue"))
const ProductIris1 = async(() => import("@/Iris/Components/IrisBlocks/Product/Ds/ProductDsIris1.vue"))
const ProductIris1Ecom = async(() => import("@/Components/CMS/Webpage/Product1/Ecommerce/ProductIris1Ecom.vue"))
const LuigiTrends1Iris = async(() => import("@/Iris/Components/IrisBlocks/LuigiTrends1Iris.vue"))
const LuigiLastSeen1Iris = async(() => import("@/Iris/Components/IrisBlocks/LuigiLastSeen1Iris.vue"))
const LuigiItemAlternatives1Iris = async(() => import("@/Iris/Components/IrisBlocks/LuigiItemAlternatives1Iris.vue"))
const AnnouncementInformation1 = async(() => import("@/Iris/Components/IrisBlocks/Announcement/AnnouncementInformation1.vue"))
const AnnouncementPromo1 = async(() => import("@/Iris/Components/IrisBlocks/Announcement/AnnouncementPromo1Iris.vue"))
const AnnouncementPromo2Countdown = async(() => import("@/Iris/Components/IrisBlocks/Announcement/AnnouncementPromo2Countdown.vue"))
const AnnouncementInformation2TransitionText = async(() => import("@/Iris/Components/IrisBlocks/Announcement/AnnouncementInformation2TransitionText.vue"))
const AnnouncementPromo3 = async(() => import("@/Iris/Components/IrisBlocks/Announcement/AnnouncementPromo3.vue"))
const RenderDropshippingProduct = async(() => import("@/Components/CMS/Webpage/Product/Dropshipping/RenderDropshippingProductIris.vue"))
const RenderEcommerceProduct = async(() => import("@/Components/CMS/Webpage/Product/Ecommerce/RenderEcommerceProductIris.vue"))

const RecommendationCRB1Iris = async(() => import("@/Iris/Components/IrisBlocks/RecommendationCRB1Iris.vue"))
const ProductIris2Ecom = async(() => import("@/Components/CMS/Webpage/Product2/ProductIris2Ecom.vue"))

const AnnouncementInformational1 = async(() => import("@/Iris/Components/IrisBlocks/Announcement/AnnouncementInformational1Iris.vue"))
const Products2Render = async(() => import("@/Iris/Components/IrisBlocks/Products/Ecom/ProductCard/ProductCardEcom2.vue"))

const Header2Iris = async(() => import("@/Iris/Components/IrisBlocks/Header2Iris.vue"))
const Header1Iris = async(() => import("@/Iris/Components/IrisBlocks/Header1Iris.vue"))

const Topbar1FulfilmentIris = async(() => import("@/Iris/Components/IrisBlocks/Topbar1FulfilmentIris.vue"))
const Topbar2FulfilmentIris = async(() => import("@/Iris/Components/IrisBlocks/Topbar2FulfilmentIris.vue"))

const Topbar1Iris = async(() => import("@/Iris/Components/IrisBlocks/Topbar1Iris.vue"))
const Topbar2Iris = async(() => import("@/Iris/Components/IrisBlocks/Topbar2Iris.vue"))


const Menu1Workshop = async(() => import("@/Components/CMS/Website/Menus/Menu1Workshop.vue"))
const Footer1Iris = async(() => import("@/Components/CMS/Website/Footers/footerTheme1/Footer1Iris.vue"))
const SeeAlso1Iris = async(() => import("@/Iris/Components/IrisBlocks/SeeAlso1Iris.vue"))
const family1Iris = async(() => import("@/Iris/Components/IrisBlocks/family1Iris.vue"))
const family2Iris = async(() => import("@/Iris/Components/IrisBlocks/family2Iris.vue"))
const family3Iris = async(() => import("@/Iris/Components/IrisBlocks/family3Iris.vue"))
const FamiliesIris1 = async(() => import("@/Iris/Components/IrisBlocks/FamiliesIris1.vue"))
const FamiliesIris2 = async(() => import("@/Iris/Components/IrisBlocks/FamiliesIris2.vue"))
const FamiliesIris3 = async(() => import("@/Iris/Components/IrisBlocks/FamiliesIris3.vue"))

const SubDepartment1Iris = async(() => import("@/Iris/Components/IrisBlocks/SubDepartmentsIris.vue"))
const SubDepartment2Iris = async(() => import("@/Iris/Components/IrisBlocks/SubDepartmentsIris2.vue"))
const SubDepartment3Iris = async(() => import("@/Iris/Components/IrisBlocks/SubDepartmentsIris3.vue"))

/* const WowsbarBannerIris = async(() => import("@/Iris/Components/IrisBlocks/WowsbarBannerIris.vue")) */
/* const ImageIris = async(() => import("@/Iris/Components/IrisBlocks/ImageIris.vue")) */
/* const TextContentIris = async(() => import("@/Iris/Components/IrisBlocks/TextContentIris.vue")) */
const CtaImageBackroundIris = async(() => import("@/Iris/Components/IrisBlocks/CtaImageBackroundIris.vue"))
const BentoGridIris = async(() => import("@/Iris/Components/IrisBlocks/BentoGridIris.vue"))
const GalleryIris = async(() => import("@/Iris/Components/IrisBlocks/GalleryIris.vue"))
const Cta1Iris = async(() => import("@/Iris/Components/IrisBlocks/Cta1Iris.vue"))
const Cta2Iris = async(() => import("@/Iris/Components/IrisBlocks/Cta2Iris.vue"))
const Cta3Iris = async(() => import("@/Iris/Components/IrisBlocks/Cta3Iris.vue"))
const IframeIris = async(() => import("@/Iris/Components/IrisBlocks/IframeIris.vue"))
const OverviewIris = async(() => import("@/Iris/Components/IrisBlocks/OverviewIris.vue"))
const Overview2Iris = async(() => import("@/Iris/Components/IrisBlocks/Overview2Iris.vue"))
const ScriptIris = async(() => import("@/Iris/Components/IrisBlocks/ScriptIris.vue"))
const CtaAurora1Iris = async(() => import("@/Iris/Components/IrisBlocks/CtaAurora1Iris.vue"))
const TextColumnIris = async(() => import("@/Iris/Components/IrisBlocks/TextColumnIris.vue"))
const PricingIris = async(() => import("@/Iris/Components/IrisBlocks/PricingIris.vue"))
const ButtonIris = async(() => import("@/Iris/Components/IrisBlocks/ButtonIris.vue"))
const ColumnIris = async(() => import("@/Iris/Components/IrisBlocks/ColumnIris.vue"))
const Column3Iris = async(() => import("@/Iris/Components/IrisBlocks/Column3Iris.vue"))
const Column4Iris = async(() => import("@/Iris/Components/IrisBlocks/Column4Iris.vue"))
const DisclosureIris = async(() => import("@/Iris/Components/IrisBlocks/DisclosureIris.vue"))
const TimelineIris = async(() => import("@/Iris/Components/IrisBlocks/TimelineIris.vue"))

const Carousel1Iris = async(() => import("@/Iris/Components/IrisBlocks/Carousel1Iris.vue"))
const CarouselCtaIris = async(() => import("@/Iris/Components/IrisBlocks/CarouselCtaIris.vue"))
const CarouselImageBackgroundIris = async(() => import("@/Iris/Components/IrisBlocks/CarouselImageBackgroundIris.vue"))
const CtaVideo1Iris = async(() => import("@/Iris/Components/IrisBlocks/CtaVideo1Iris.vue"))
const Video1Iris = async(() => import("@/Iris/Components/IrisBlocks/Video1Iris.vue"))
const UserSubscribeIris = async(() => import("@/Iris/Components/IrisBlocks/UserSubscribeIris.vue"))
const Cta4Iris = async(() => import("@/Iris/Components/IrisBlocks/Cta4Iris.vue"))
const BlogIris = async(() => import("@/Iris/Components/IrisBlocks/BlogIris.vue"))
const Step2Iris = async(() => import("@/Iris/Components/IrisBlocks/Step2Iris.vue"))
const Step1Iris = async(() => import("@/Iris/Components/IrisBlocks/Step1Iris.vue"))
const Slider1Iris = async(() => import("@/Iris/Components/IrisBlocks/Slider1Iris.vue"))
const CollectionDescriptionIris = async(() => import("@/Iris/Components/IrisBlocks/CollectionDescriptionIris.vue"))
const ProductRenderEcom3 = async(() => import("@/Iris/Components/IrisBlocks/Products/Ecom/ProductCard/ProductCardEcom3.vue"))
const Family2ExtraDescriptionIris = async(() => import("@/Iris/Components/IrisBlocks/Family2ExtraDescriptionIris.vue"))
const Families1Overview = async(() => import("@/Iris/Components/IrisBlocks/FamiliesOverviewIris1.vue"))
const RecommendationFromMasterIris = async(() => import("@/Iris/Components/IrisBlocks/RecommendationFromMasterIris.vue"))
const RealatedProductCategoryIris = async(() => import("@/Iris/Components/IrisBlocks/RealatedProductCategoryIris.vue"))
const RelatedProductcategoryFormMaster = async(() => import("@/Iris/Components/IrisBlocks/RecommendationProductCategoryFromMasterIris.vue"))
const TabsIris = async(() => import("@/Iris/Components/IrisBlocks/TabsIris.vue"))
const FaqDepartment = async(() => import("@/Iris/Components/IrisBlocks/FaqDepartment.vue"))
const TopFamiliesIris = async(() => import("@/Iris/Components/IrisBlocks/TopFamiliesIris.vue"))

const components = (shop_type?: string): Record<string, Component> => {
	return {
		//topBar
		"top-bar-1": Topbar1Iris,
		"top-bar-2": Topbar2Iris,

		"top-bar-1-fulfilment": Topbar1FulfilmentIris,
		"top-bar-2-fulfilment": Topbar2FulfilmentIris,

		//header
		"header-1": Header1Iris,
		"header-2": Header2Iris,


		//menu
		"menu-1": Menu1Workshop,

		//footer
		"footer-1": Footer1Iris,

		 //description catalogue
		'collection-description-1' : CollectionDescriptionIris,
		'department-description-1' : DepartmentDescriptionIris,
		'department-description-2' : DepartmentDescriptionIris2,
		'sub-department-description-1' : SubDepartmentDescriptionIris,

		//sub-department
		"sub-departments-1": SubDepartment1Iris,
		"sub-departments-2": SubDepartment2Iris,
		"sub-departments-3": SubDepartment3Iris,

		//family
		"families-1": FamiliesIris1,
		"families-2": FamiliesIris2,
		"families-3": FamiliesIris3,


		//family
		"families-1-overview": Families1Overview,

		// family-description
		'family-1': family1Iris,
		'family-2': family2Iris,
		'family-3': family3Iris,

		 //family-extra-description
		'family-2-extra-description' : Family2ExtraDescriptionIris,
		'family-3-extra-description' : Family2ExtraDescriptionIris,

		//product
		"product-1": shop_type === "b2b" ? RenderEcommerceProduct : RenderDropshippingProduct,

		"product-2": RenderEcommerceProduct,

		//product list
		"products-1": shop_type === "b2b" ? ListProductsEcomIris : ListProductsIris,
		"products-2": ListProductsEcomIris,

		//see-also
		"see-also-1": SeeAlso1Iris,

		// Luigi
		"luigi-trends-1": LuigiTrends1Iris,
		"luigi-last-seen-1": LuigiLastSeen1Iris,
		"luigi-item-alternatives-1": LuigiItemAlternatives1Iris,
		"recommendation-customer-recently-bought-1": RecommendationCRB1Iris,

		"cta-image-background": CtaImageBackroundIris,
		banner: WowsbarBannerIris,
		"bento-grid-1": BentoGridIris,
		bricks: GalleryIris,
		cta1: Cta1Iris,
		cta2: Cta2Iris,
		cta3: Cta3Iris,
		iframe: IframeIris,
		images: ImageIris,
		overview_aurora: OverviewIris,
		overview_2: Overview2Iris,
		script: ScriptIris,
		text: TextContentIris,
		cta_aurora_1: CtaAurora1Iris,
		"text-column": TextColumnIris,
		pricing: PricingIris,
		button: ButtonIris,
		"column-layout-2": ColumnIris,
		"column-layout-3": Column3Iris,
		"column-layout-4": Column4Iris,
		disclosure: DisclosureIris,
		timeline: TimelineIris,

		"carousel-1": Carousel1Iris,
		"carousel-cta": CarouselCtaIris,
		"carousel-image-background": CarouselImageBackgroundIris,
		"step-1": Step1Iris,
		"cta-video-1": CtaVideo1Iris,
		video_1: Video1Iris,
		"user-subscribe": UserSubscribeIris,
		cta4: Cta4Iris,
		blog: BlogIris,
		"step-2": Step2Iris,
		'video-1': Video1Iris,
		'slider-1' : Slider1Iris,
		'recommendation-from-master' : RecommendationFromMasterIris,
	    'relatedProductCategory' : RealatedProductCategoryIris,
		'recommendation-product-category-from-master' : RelatedProductcategoryFormMaster,
		'tabs' : TabsIris,
		'faq-department' : FaqDepartment,
		'top-families' : TopFamiliesIris
	}
}

export const getIrisComponent = (
	componentName: string,
	options?: {
		shop_type?: string // 'b2b' | 'dropshipping'
	}
) => {
	return components(options?.shop_type)[componentName] ?? NotFoundComponent
}

export const getProductsRenderDropshippingComponent = (
	componentName: string,
	options: Record<string, any> = {}
) => {
	const components: Record<string, any> = {
		"products-1": ProductRender,
	}

	return components[componentName] ?? null
}

export const getProductsRenderB2bComponent = (
	componentName: string,
	options: Record<string, any> = {}
) => {
	const components: Record<string, any> = {
		//"products-1": ProductRenderEcom, old
		"products-1": ProductRenderEcom3,
		"products-2": Products2Render,
	}

	return components[componentName] ?? null
}

export const getProductRenderB2bComponent = (
	componentName: string,
	options: Record<string, any> = {}
) => {
	const components: Record<string, any> = {
		"product-1": ProductIris1Ecom,
		"product-2": ProductIris2Ecom,
	}

	return components[componentName] ?? null
}


export const getProductRenderDropshippingComponent = (
	componentName: string,
	options: Record<string, any> = {}
) => {
	const components: Record<string, any> = {
		"product-1": ProductIris1,
	}

	return components[componentName] ?? null
}


export const announcementComponent = (shop_type?: string): Record<string, Component> => {
    return {
        'announcement-information-1': AnnouncementInformation1,
        'announcement-informational-1': AnnouncementInformational1,
        'announcement-promo-1': AnnouncementPromo1,
        'announcement-promo-3': AnnouncementPromo3,
        'announcement-promo-2-countdown': AnnouncementPromo2Countdown,
        'announcement-information-2-transition-text': AnnouncementInformation2TransitionText,

    }
}


export const getIrisAnnouncementComponent = (
	componentName: string,
	options?: {
		shop_type?: string // 'b2b' | 'dropshipping'
	}
) => {
	return announcementComponent(options?.shop_type)[componentName] ?? NotFoundComponent
}
