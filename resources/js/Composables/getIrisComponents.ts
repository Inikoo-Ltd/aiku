import type { Component } from "vue"
import { defineAsyncComponent } from "vue"


// import Footer1Iris from "@/Components/CMS/Website/Footers/footerTheme1/Footer1Iris.vue"
// import Header1Iris from "@/Components/CMS/Website/Headers/Header1/Header1Iris.vue"
// import Header2Iris from "@/Components/CMS/Website/Headers/Header2/Header2Iris.vue"
// import Topbar1Iris from "@/Components/CMS/Website/TopBars/Template/Topbar1/Topbar1Iris.vue"
// import Topbar2Iris from "@/Components/CMS/Website/TopBars/Template/Topbar2/Topbar2Iris.vue"
// import Topbar3Iris from "@/Components/CMS/Website/TopBars/Template/Topbar3/Topbar3Iris.vue"
// import Menu1Workshop from "@/Components/CMS/Website/Menus/Menu1Workshop.vue"
// import WowsbarBannerIris from "@/Components/CMS/Webpage/WowsbarBanner/WowsbarBannerIris.vue"
// import BentoGridIris from "@/Components/CMS/Webpage/BentoGrid/BentoGridIris.vue"
// import GalleryIris from "@/Components/CMS/Webpage/Gallery/GalleryIris.vue"
/* import CTAIris from "@/Components/CMS/Webpage/Cta1/Cta1Iris.vue"
import CTA2Iris from "@/Components/CMS/Webpage/Cta2/Cta2Iris.vue"
import CTA3Iris from "@/Components/CMS/Webpage/Cta3/Cta3Iris.vue" */
// import Department1Iris from "@/Components/CMS/Webpage/Department1/Department1Iris.vue"
/* import IframeIris from "@/Components/CMS/Webpage/Iframe/IframeIris.vue" */
/* import ImageIris from "@/Components/CMS/Webpage/Image/ImageIris.vue" */
// import OverviewIris from "@/Components/CMS/Webpage/Overview/OverviewIris.vue"
// import ScriptIris from "@/Components/CMS/Webpage/Script/ScriptIris.vue"
/* import TextContentIris from "@/Components/CMS/Webpage/Text/TextContentIris.vue" */
// import CtaAurora1Iris from "@/Components/CMS/Webpage/CtaAurora1/CtaAurora1Iris.vue"
// import Overview2Iris from "@/Components/CMS/Webpage/Overview2/Overview2Iris.vue"
// import Pricing from "@/Components/CMS/Webpage/Pricing/PricingIris.vue"
// import Timeline from "@/Components/CMS/Webpage/Timeline/TimelineIris.vue"
// import TextColumnIris from "@/Components/CMS/Webpage/TextColumn/TextColumnIris.vue"
// import Topbar1FulfilmentIris from "@/Components/CMS/Website/TopBars/Template/Topbar1Fulfilment/Topbar1FulfilmentIris.vue"
// import Topbar2FulfilmentIris from "@/Components/CMS/Website/TopBars/Template/Topbar2Fulfilment/Topbar2FulfilmentIris.vue"
// import Topbar3FulfilmentIris from "@/Components/CMS/Website/TopBars/Template/Topbar3Fulfilment/Topbar3FulfilmentIris.vue"
// import ButtonIris from "@/Components/CMS/Webpage/Button/ButtonIris.vue"
// import ColumnIris from "@/Components/CMS/Webpage/Column/ColumnIris.vue"
// import DisclosureIris from "@/Components/CMS/Webpage/Disclosure/DisclosureIris.vue"
import NotFoundComponent from "@/Components/CMS/Webpage/NotFoundComponent.vue"

import ListProductsIris from "@/Components/CMS/Webpage/Products/Dropshipping/ListProductsIris.vue"
import ProductRender from "@/Components/CMS/Webpage/Products1/Dropshipping/ProductRender.vue"
import ProductRenderEcom from "@/Components/CMS/Webpage/Products1/Ecommerce/ProductRenderEcom.vue"
import ListProductsEcomIris from "@/Components/CMS/Webpage/Products/Ecommerce/ListProductsEcomIris.vue"

// import FamilyIris1 from '@/Components/CMS/Webpage/Family-1/family1Iris.vue'
import ProductIris1 from '@/Components/CMS/Webpage/Product1/Dropshipping/ProductIris1.vue'
import ProductIris1Ecom from '@/Components/CMS/Webpage/Product1/Ecommerce/ProductIris1Ecom.vue'
// import Carousel1Iris from '@/Components/CMS/Webpage/Carousel-1/Carousel1Iris.vue'
// import SubDepartmentIris from '@/Components/CMS/Webpage/SubDepartment1/SubDepartmentIris.vue'
// import Collections1Iris from '@/Components/CMS/Webpage/Collections1/Collections1Iris.vue'
/* import CTAVideo1Iris from '@/Components/CMS/Webpage/CtaVideo1/CtaVideo1Iris.vue' */
// import Video1Iris from '@/Components/CMS/Webpage/Video/Video1Iris.vue'
// import families1Iris from '@/Components/CMS/Webpage/Families1/FamiliesIris1.vue'
// import BlogIris from '@/Components/CMS/Webpage/Blog/BlogIris.vue'
// import CarouselCtaIris from '@/Components/CMS/Webpage/CarouselCta/CarouselCtaIris.vue'
// import CarouselImageBackgroundIris from '@/Components/CMS/Webpage/CarouselImageBackground/CarouselImageBackgroundIris.vue'

// import Cta4 from '@/Components/CMS/Webpage/Cta4/Cta4Iris.vue'

// import SeeAlso1WorkshopIris from '@/Components/CMS/Webpage/SeeAlso1/SeeAlso1Iris.vue'
import LuigiTrends1Iris from '@/Components/CMS/Webpage/LuigiTrends1/LuigiTrends1Iris.vue'
import Step2Iris from '@/Components/CMS/Webpage/Step2/Step2Iris.vue'

// import UserSubscribeIris from '@/Components/CMS/Webpage/UserSubscribe/UserSubscribeIris.vue'
import LuigiLastSeen1Iris from '@/Components/CMS/Webpage/LuigiLastSeen1/LuigiLastSeen1Iris.vue'
import LuigiItemAlternatives1Iris from '@/Components/CMS/Webpage/LuigiItemAlternatives1/LuigiItemAlternatives1Iris.vue'
import AnnouncementInformation1 from '@/Components/Websites/Announcement/Templates/Information/AnnouncementInformation1.vue'
import AnnouncementPromo1 from '@/Components/Websites/Announcement/Templates/Promo/AnnouncementPromo1.vue'
import AnnouncementPromo2Countdown from '@/Components/Websites/Announcement/Templates/Promo/AnnouncementPromo2Countdown.vue'
import AnnouncementInformation2TransitionText from '@/Components/Websites/Announcement/Templates/Information/AnnouncementInformation2TransitionText.vue'
import AnnouncemenPromo3 from '@/Components/Websites/Announcement/Templates/Promo/AnnouncementPromo3.vue'
import RenderDropshippingProduct from "@/Components/CMS/Webpage/Product/Dropshipping/RenderDropshippingProductIris.vue"
import RenderEcommerceProduct from "@/Components/CMS/Webpage/Product/Ecommerce/RenderEcommerceProductIris.vue"

import RecommendationCustomerRecentlyBought1Iris from '@/Components/CMS/Webpage/RecomendationRecentlyBought1/RecommendationCustomerRecentlyBought1Iris.vue'
// import CtaImageBackroundIris from '@/Components/CMS/Webpage/CtaImageBackround/CtaImageBackroundIris.vue'
// import SubDepartement2 from '@/Components/CMS/Webpage/SubDepartment2/SubDepartmentIris.vue'
// import FamiliesIris2 from "@/Components/CMS/Webpage/Families2/FamiliesIris2.vue"
import ProductIris2Ecom from "@/Components/CMS/Webpage/Product2/ProductIris2Ecom.vue"


import AnnouncementInformational1 from '@/Components/Websites/Announcement/Templates/Information/AnnouncementInformational1.vue'
import Products2Render from "@/Components/CMS/Webpage/Products2/Products2Render.vue"

const async = (loader: () => Promise<Component>) =>
	defineAsyncComponent({
		loader,
		delay: 200,
		timeout: 15000,
	})

const components = (shop_type?: string): Record<string, Component> => {
	return {
		//topBar
		"top-bar-1": async(() => import("@/Components/CMS/Website/TopBars/Template/Topbar1/Topbar1Iris.vue")),
		"top-bar-2": async(() => import("@/Components/CMS/Website/TopBars/Template/Topbar2/Topbar2Iris.vue")),
		"top-bar-3": async(() => import("@/Components/CMS/Website/TopBars/Template/Topbar3/Topbar3Iris.vue")),

		"top-bar-1-fulfilment": async(() => import("@/Components/CMS/Website/TopBars/Template/Topbar1Fulfilment/Topbar1FulfilmentIris.vue")),
		"top-bar-2-fulfilment": async(() => import("@/Components/CMS/Website/TopBars/Template/Topbar2Fulfilment/Topbar2FulfilmentIris.vue")),
		"top-bar-3-fulfilment": async(() => import("@/Components/CMS/Website/TopBars/Template/Topbar3Fulfilment/Topbar3FulfilmentIris.vue")),

			//header
		"header-1": async(() => import("@/Components/CMS/Website/Headers/Header1/Header1Iris.vue")),
		"header-2": async(() => import("@/Components/CMS/Website/Headers/Header2/Header2Iris.vue")),
		

			//menu
		"menu-1": async(() => import("@/Components/CMS/Website/Menus/Menu1Workshop.vue")),

			//footer
		"footer-1": async(() => import("@/Components/CMS/Website/Footers/footerTheme1/Footer1Iris.vue")),
			//department
		department: async(() => import("@/Components/CMS/Webpage/Department1/Department1Iris.vue")),
		"department-1": async(() => import("@/Components/CMS/Webpage/Department1/Department1Iris.vue")),

		//sub-department	
		"sub-departments-1": async(() => import("@/Components/CMS/Webpage/SubDepartment1/SubDepartmentIris.vue")),
		"sub-departments-2": async(() => import("@/Components/CMS/Webpage/SubDepartment2/SubDepartmentIris.vue")),

		//family
		"family-1": async(() => import("@/Components/CMS/Webpage/Family-1/family1Iris.vue")),
		"families-1": async(() => import("@/Components/CMS/Webpage/Families1/FamiliesIris1.vue")),
		"families-2": async(() => import("@/Components/CMS/Webpage/Families2/FamiliesIris2.vue")),

		//product
		"product-1": shop_type === "b2b"
		? RenderEcommerceProduct
		: RenderDropshippingProduct,

		"product-2": RenderEcommerceProduct,

		//product list
		"products-1": shop_type === "b2b"
		? ListProductsEcomIris
		: ListProductsIris,

		"products-2": ListProductsEcomIris,


		//see-also
		"see-also-1": async(() => import("@/Components/CMS/Webpage/SeeAlso1/SeeAlso1Iris.vue")),

		// Luigi
		"luigi-trends-1": LuigiTrends1Iris,
		"luigi-last-seen-1": LuigiLastSeen1Iris,
		"luigi-item-alternatives-1": LuigiItemAlternatives1Iris,
		"recommendation-customer-recently-bought-1": RecommendationCustomerRecentlyBought1Iris,

		"cta-image-background": async(() => import("@/Components/CMS/Webpage/CtaImageBackround/CtaImageBackroundIris.vue")),
		banner: async(() => import("@/Components/CMS/Webpage/WowsbarBanner/WowsbarBannerIris.vue")),
		"bento-grid-1": async(() => import("@/Components/CMS/Webpage/BentoGrid/BentoGridIris.vue")),
		bricks: async(() => import("@/Components/CMS/Webpage/Gallery/GalleryIris.vue")),
		cta1: async(() => import("@/Components/CMS/Webpage/Cta1/Cta1Iris.vue")),
		cta2: async(() => import("@/Components/CMS/Webpage/Cta2/Cta2Iris.vue")),
		cta3: async(() => import("@/Components/CMS/Webpage/Cta3/Cta3Iris.vue")),
		iframe: async(() => import("@/Components/CMS/Webpage/Iframe/IframeIris.vue")),
		images:  async(() => import('@/Components/CMS/Webpage/Image/ImageIris.vue')),
		overview_aurora: async(() => import("@/Components/CMS/Webpage/Overview/OverviewIris.vue")),
	    overview_2: async(() => import("@/Components/CMS/Webpage/Overview2/Overview2Iris.vue")),
		script: async(() => import("@/Components/CMS/Webpage/Script/ScriptIris.vue")),
		text: async(() => import("@/Components/CMS/Webpage/Text/TextContentIris.vue")),
		cta_aurora_1: async(() => import("@/Components/CMS/Webpage/CtaAurora1/CtaAurora1Iris.vue")),
		"text-column": async(() => import("@/Components/CMS/Webpage/TextColumn/TextColumnIris.vue")),
		pricing: async(() => import("@/Components/CMS/Webpage/Pricing/PricingIris.vue")),
		button: async(() => import("@/Components/CMS/Webpage/Button/ButtonIris.vue")),
		"column-layout-2": async(() => import("@/Components/CMS/Webpage/Column/ColumnIris.vue")),
		disclosure: async(() => import("@/Components/CMS/Webpage/Disclosure/DisclosureIris.vue")),
		timeline: async(() => import("@/Components/CMS/Webpage/Timeline/TimelineIris.vue")),

		"carousel-1": async(() => import("@/Components/CMS/Webpage/Carousel-1/Carousel1Iris.vue")),
		"carousel-cta": async(() => import("@/Components/CMS/Webpage/CarouselCta/CarouselCtaIris.vue")),
		"carousel-image-background": async(() => import("@/Components/CMS/Webpage/CarouselImageBackground/CarouselImageBackgroundIris.vue")),
		"step-1": Step2Iris,
		"collections-1": async(() => import("@/Components/CMS/Webpage/Collections1/Collections1Iris.vue")),
		"cta-video-1":  async(() => import('@/Components/CMS/Webpage/CtaVideo1/CtaVideo1Iris.vue')),
		video_1: async(() => import("@/Components/CMS/Webpage/Video/Video1Iris.vue")),
		"user-subscribe": async(() => import("@/Components/CMS/Webpage/UserSubscribe/UserSubscribeIris.vue")),
		cta4: async(() => import("@/Components/CMS/Webpage/Cta4/Cta4Iris.vue")),
		blog: async(() => import("@/Components/CMS/Webpage/Blog/BlogIris.vue")),
		"step-2": async(() => import("@/Components/CMS/Webpage/Step2/Step2Iris.vue")),
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
		"products-1": ProductRenderEcom,
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
        'announcement-promo-3': AnnouncemenPromo3,
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
