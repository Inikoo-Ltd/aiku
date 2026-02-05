import type { Component } from "vue"
import { defineAsyncComponent } from "vue"



import NotFoundComponent from "@/Components/CMS/Webpage/NotFoundComponent.vue"

import ListProductsIris from "@/Components/CMS/Webpage/Products/Dropshipping/ListProductsIris.vue"
import ProductRender from "@/Components/CMS/Webpage/Products1/Dropshipping/ProductRender.vue"
import ProductRenderEcom from "@/Components/CMS/Webpage/Products1/Ecommerce/ProductRenderEcom.vue"
import ListProductsEcomIris from "@/Components/CMS/Webpage/Products/Ecommerce/ListProductsEcomIris.vue"
import ProductIris1 from '@/Components/CMS/Webpage/Product1/Dropshipping/ProductIris1.vue'
import ProductIris1Ecom from '@/Components/CMS/Webpage/Product1/Ecommerce/ProductIris1Ecom.vue'
import LuigiTrends1Iris from '@/Components/CMS/Webpage/LuigiTrends1/LuigiTrends1Iris.vue'
import LuigiLastSeen1Iris from '@/Components/CMS/Webpage/LuigiLastSeen1/LuigiLastSeen1Iris.vue'
import LuigiItemAlternatives1Iris from '@/Components/CMS/Webpage/LuigiItemAlternatives1/LuigiItemAlternatives1Iris.vue'
import AnnouncementInformation1 from '@/Components/Websites/Announcement/Templates/Information/AnnouncementInformation1.vue'
import AnnouncementPromo1 from '@/Components/Websites/Announcement/Templates/Promo/AnnouncementPromo1.vue'
import AnnouncementPromo2Countdown from '@/Components/Websites/Announcement/Templates/Promo/AnnouncementPromo2Countdown.vue'
import AnnouncementInformation2TransitionText from '@/Components/Websites/Announcement/Templates/Information/AnnouncementInformation2TransitionText.vue'
import AnnouncementPromo3 from '@/Components/Websites/Announcement/Templates/Promo/AnnouncementPromo3.vue'
import RenderDropshippingProduct from "@/Components/CMS/Webpage/Product/Dropshipping/RenderDropshippingProductIris.vue"
import RenderEcommerceProduct from "@/Components/CMS/Webpage/Product/Ecommerce/RenderEcommerceProductIris.vue"

import RecommendationCRB1Iris from '@/Components/CMS/Webpage/RecomendationRecentlyBought1/RecommendationCRB1Iris.vue'
import ProductIris2Ecom from "@/Components/CMS/Webpage/Product2/ProductIris2Ecom.vue"


import AnnouncementInformational1 from '@/Components/Websites/Announcement/Templates/Information/AnnouncementInformational1.vue'
import Products2Render from "@/Components/CMS/Webpage/Products2/Products2Render.vue"


import Header2Iris from "@/Components/CMS/Website/Headers/Header2/Header2Iris.vue"
import Header1Iris from "@/Components/CMS/Website/Headers/Header1/Header1Iris.vue"

import Topbar1FulfilmentIris from "@/Components/CMS/Website/TopBars/Template/Topbar1Fulfilment/Topbar1FulfilmentIris.vue"
import Topbar2FulfilmentIris from "@/Components/CMS/Website/TopBars/Template/Topbar2Fulfilment/Topbar2FulfilmentIris.vue"
import Topbar3FulfilmentIris from "@/Components/CMS/Website/TopBars/Template/Topbar3Fulfilment/Topbar3FulfilmentIris.vue"

import Topbar1Iris from "@/Components/CMS/Website/TopBars/Template/Topbar1/Topbar1Iris.vue"
import Topbar2Iris from "@/Components/CMS/Website/TopBars/Template/Topbar2/Topbar2Iris.vue"
import Topbar3Iris from "@/Components/CMS/Website/TopBars/Template/Topbar3/Topbar3Iris.vue"


import Menu1Workshop from "@/Components/CMS/Website/Menus/Menu1Workshop.vue"


import Footer1Iris from "@/Components/CMS/Website/Footers/footerTheme1/Footer1Iris.vue"

import SeeAlso1Iris from "@/Components/CMS/Webpage/SeeAlso1/SeeAlso1Iris.vue"

import family1Iris from "@/Components/CMS/Webpage/Family-1/family1Iris.vue"
import FamiliesIris1 from "@/Components/CMS/Webpage/Families1/FamiliesIris1.vue"
import FamiliesIris2 from "@/Components/CMS/Webpage/Families2/FamiliesIris2.vue"

import Department1Iris from "@/Components/CMS/Webpage/Department1/Department1Iris.vue"

import SubDepartment1Iris from "@/Components/CMS/Webpage/SubDepartment1/SubDepartmentIris.vue"
import SubDepartment2Iris from "@/Components/CMS/Webpage/SubDepartment2/SubDepartmentIris.vue"

import CtaImageBackroundIris from "@/Components/CMS/Webpage/CtaImageBackround/CtaImageBackroundIris.vue"
import WowsbarBannerIris from "@/Components/CMS/Webpage/WowsbarBanner/WowsbarBannerIris.vue"
import BentoGridIris from "@/Components/CMS/Webpage/BentoGrid/BentoGridIris.vue"
import GalleryIris from "@/Components/CMS/Webpage/Gallery/GalleryIris.vue"
import Cta1Iris from "@/Components/CMS/Webpage/Cta1/Cta1Iris.vue"
import Cta2Iris from "@/Components/CMS/Webpage/Cta2/Cta2Iris.vue"
import Cta3Iris from "@/Components/CMS/Webpage/Cta3/Cta3Iris.vue"
import IframeIris from "@/Components/CMS/Webpage/Iframe/IframeIris.vue"
import ImageIris from "@/Components/CMS/Webpage/Image/ImageIris.vue"
import OverviewIris from "@/Components/CMS/Webpage/Overview/OverviewIris.vue"
import Overview2Iris from "@/Components/CMS/Webpage/Overview2/Overview2Iris.vue"
import ScriptIris from "@/Components/CMS/Webpage/Script/ScriptIris.vue"
import TextContentIris from "@/Components/CMS/Webpage/Text/TextContentIris.vue"
import CtaAurora1Iris from "@/Components/CMS/Webpage/CtaAurora1/CtaAurora1Iris.vue"
import TextColumnIris from "@/Components/CMS/Webpage/TextColumn/TextColumnIris.vue"
import PricingIris from "@/Components/CMS/Webpage/Pricing/PricingIris.vue"
import ButtonIris from "@/Components/CMS/Webpage/Button/ButtonIris.vue"
import ColumnIris from "@/Components/CMS/Webpage/Column/ColumnIris.vue"
import Column3Iris from "@/Components/CMS/Webpage/Column3/Column3Iris.vue"
import Column4Iris from "@/Components/CMS/Webpage/Column4/Column4Iris.vue"
import DisclosureIris from "@/Components/CMS/Webpage/Disclosure/DisclosureIris.vue"
import TimelineIris from "@/Components/CMS/Webpage/Timeline/TimelineIris.vue"

import Carousel1Iris from "@/Components/CMS/Webpage/Carousel-1/Carousel1Iris.vue"
import CarouselCtaIris from "@/Components/CMS/Webpage/CarouselCta/CarouselCtaIris.vue"
import CarouselImageBackgroundIris from "@/Components/CMS/Webpage/CarouselImageBackground/CarouselImageBackgroundIris.vue"
import Collections1Iris from "@/Components/CMS/Webpage/Collections1/Collections1Iris.vue"
import CtaVideo1Iris from "@/Components/CMS/Webpage/CtaVideo1/CtaVideo1Iris.vue"
import Video1Iris from "@/Components/CMS/Webpage/Video/Video1Iris.vue"
import UserSubscribeIris from "@/Components/CMS/Webpage/UserSubscribe/UserSubscribeIris.vue"
import Cta4Iris from "@/Components/CMS/Webpage/Cta4/Cta4Iris.vue"
import BlogIris from "@/Components/CMS/Webpage/Blog/BlogIris.vue"
import Step2Iris from "@/Components/CMS/Webpage/Step2/Step2Iris.vue"
import Video1Iris from "@/Components/CMS/Webpage/Video/Video1Iris.vue"


const async = (loader: () => Promise<Component>) =>
	defineAsyncComponent({
		loader,
		delay: 200,
		timeout: 15000,
	})

const components = (shop_type?: string): Record<string, Component> => {
	return {
		//topBar
		"top-bar-1": Topbar1Iris,
		"top-bar-2": Topbar2Iris,
		"top-bar-3": Topbar3Iris,

		"top-bar-1-fulfilment": Topbar1FulfilmentIris,
		"top-bar-2-fulfilment": Topbar2FulfilmentIris,
		"top-bar-3-fulfilment": Topbar3FulfilmentIris,

			//header
		"header-1": Header1Iris,
		"header-2": Header2Iris,
		

			//menu
		"menu-1": Menu1Workshop,

			//footer
		"footer-1": Footer1Iris,

			//department
		department: Department1Iris,
		"department-1": Department1Iris,

		//sub-department	
		"sub-departments-1": SubDepartment1Iris,
		"sub-departments-2": SubDepartment2Iris,

		//family
		"family-1": family1Iris,
		"families-1": FamiliesIris1,
		"families-2": FamiliesIris2,

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
		"step-1": Step2Iris,
		"collections-1": Collections1Iris,
		"cta-video-1": CtaVideo1Iris,
		video_1: Video1Iris,
		"user-subscribe": UserSubscribeIris,
		cta4: Cta4Iris,
		blog: BlogIris,
		"step-2": Step2Iris,
		'video-1': Video1Iris,
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
