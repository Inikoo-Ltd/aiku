import type { Component } from "vue"

import Footer1Blueprint from "@/Components/CMS/Website/Footers/footerTheme1/bluprint"
import Topbar1Blueprint from "@/Components/CMS/Website/TopBars/Template/Topbar1/Blueprint"
import Topbar2Blueprint from "@/Components/CMS/Website/TopBars/Template/Topbar2/Blueprint"
import Topbar3Blueprint from "@/Components/CMS/Website/TopBars/Template/Topbar3/Blueprint"
import Header1Blueprint from "@/Components/CMS/Website/Headers/Header1/Blueprint"
import Header2Blueprint from "@/Components/CMS/Website/Headers/Header2/Blueprint"
import BentoGridBlueprint from "@/Components/CMS/Webpage/BentoGrid/Blueprint"
import CTA2Blueprint from "@/Components/CMS/Webpage/CTA2/Blueprint"
import CategoriesBlueprint from "@/Components/CMS/Webpage/Categories/Blueprint"
import CTA3Blueprint from "@/Components/CMS/Webpage/CTA3/Blueprint"
import { blueprint as CTAAurora1Blueprint } from "@/Components/CMS/Webpage/CTAAurora1/Blueprint"
import CTABlueprint from "@/Components/CMS/Webpage/CTA/Blueprint"
import DepartmentBlueprint from "@/Components/CMS/Webpage/Department1/Blueprint"
import GalleryBlueprint from "@/Components/CMS/Webpage/Gallery/Blueprint"
import IframeBlueprint from "@/Components/CMS/Webpage/Iframe/Blueprint"
import ImageBlueprint from "@/Components/CMS/Webpage/Image/Blueprint"
import Overview2Blueprint from "@/Components/CMS/Webpage/Overview2/Blueprint"
import OverviewBlueprint from "@/Components/CMS/Webpage/Overview/Blueprint"
import ReviewsBlueprint from "@/Components/CMS/Webpage/Reviews/Blueprint"
import TextBlueprint from "@/Components/CMS/Webpage/Text/Blueprint"
import ScriptBlueprint from "@/Components/CMS/Webpage/Script/Blueprint"
import WowsbarBannerBlueprint from "@/Components/CMS/Webpage/WowsbarBanner/Blueprint"
import SeeAlsoBlueprint from "@/Components/CMS/Webpage/SeeAlso/Blueprint"
import PricingBlueprint from "@/Components/CMS/Webpage/Pricing/Blueprint"
import TimelineBlueprint from "@/Components/CMS/Webpage/Timeline/Blueprint"
import TextColumn from "@/Components/CMS/Webpage/TextColumn/Blueprint"
import Topbar1Fulfilment from "@/Components/CMS/Website/TopBars/Template/Topbar1Fulfilment/Blueprint"
import Topbar2Fulfilment from "@/Components/CMS/Website/TopBars/Template/Topbar2Fulfilment/Blueprint"
import Topbar3Fulfilment from "@/Components/CMS/Website/TopBars/Template/Topbar3Fulfilment/Blueprint"
import Button from "@/Components/CMS/Webpage/Button/Blueprint"
import ColumnLayout2Blueprint from "@/Components/CMS/Webpage/Column/Blueprint"
import DisclosureBlueprint from "@/Components/CMS/Webpage/Disclosure/Blueprint"
import FamilyBluprint from "@/Components/CMS/Webpage/Families1/Blueprint"
import DepartementBlueprint from "@/Components/CMS/Webpage/Department1/Blueprint"
import Timeline2Bluprint from "@/Components/CMS/Webpage/Step1/Blueprint"
import Carousel1Blueprint from "@/Components/CMS/Webpage/Carousel-1/Blueprint"
import SubDepartement1Blueprint from "@/Components/CMS/Webpage/SubDepartement1/Blueprint"
import Product1Blueprint from "@/Components/CMS/Webpage/Product1/Blueprint"
import ProductsList1Blueprint from '@/Components/CMS/Webpage/Products1/Blueprint.ts'
import VideoBlueprint from '@/Components/CMS/Webpage/Video/Blueprint'
import CTAVideo1Blueprint from '@/Components/CMS/Webpage/CTAVideo1/Blueprint'

import { data } from "autoprefixer"
import CTAVideo1Workshop from "@/Components/CMS/Webpage/CTAVideo1/CTAVideo1Workshop.vue"

export const getBlueprint = (componentName: string) => {
	const components: Component = {
		// topbar
		"top-bar-1-fulfilment": Topbar1Fulfilment.blueprint,
		"top-bar-2-fulfilment": Topbar2Fulfilment.blueprint,
		"top-bar-3-fulfilment": Topbar3Fulfilment.blueprint,
		"top-bar-1": Topbar1Blueprint.blueprint,
		"top-bar-2": Topbar2Blueprint.blueprint,
		"top-bar-3": Topbar3Blueprint.blueprint,

		//header
		"header-1": Header1Blueprint.blueprint,
		"header-2": Header2Blueprint.blueprint,

		//footer
		"footer-1": Footer1Blueprint.blueprint,
		
		//depeartement
		"departments": DepartementBlueprint.blueprint,
		"department-1": DepartementBlueprint.blueprint,

		//sub-departement
		"sub-departments-1": SubDepartement1Blueprint.blueprint,

		//family
		"family-1": FamilyBluprint.blueprint,

		//families list
		"families-1": FamilyBluprint.blueprint,

		//products-list
		'products-1' : ProductsList1Blueprint.blueprint,


		"banner": WowsbarBannerBlueprint.blueprint,
		"bento-grid-1": BentoGridBlueprint.blueprint,
		"bricks": GalleryBlueprint.blueprint,
		"cta1": CTABlueprint.blueprint,
		"cta2": CTA2Blueprint.blueprint,
		"cta3": CTA3Blueprint.blueprint,
		"text-column": TextColumn.blueprint,
		"iframe": IframeBlueprint.blueprint,
		"images": ImageBlueprint.blueprint,
		"overview_aurora": OverviewBlueprint.blueprint,
		"pricing": PricingBlueprint.blueprint,
		"product-1": Product1Blueprint.blueprint,
		"script": ScriptBlueprint.blueprint,
		"text": TextBlueprint.blueprint,
		"cta_aurora_1": CTAAurora1Blueprint,
		"overview_2": Overview2Blueprint.blueprint,
		"button": Button.blueprint,
		"column-layout-2": ColumnLayout2Blueprint.blueprint,
		"disclosure": DisclosureBlueprint.blueprint,
		"timeline": TimelineBlueprint.blueprint,
		"step-1": Timeline2Bluprint.blueprint,
		"carousel-1": Carousel1Blueprint.blueprint,
		"cta-video-1" : CTAVideo1Blueprint.blueprint,
		'video-1': VideoBlueprint.blueprint
	}
	return components[componentName] ?? []
}

export const getBluprintPermissions = (componentName: string) => {
	const components: Component = {
		"departments": false,
		"family-1": false,
		"families-1": false,
		"product": false,
		"product-1": false,
		"family": false,
		"families": false,
		"sub-departments-1": false,
		"department-1": false,
		"products-1": false,
	}
	return components[componentName] ?? true
}


type PermissionData = {
  permissions?: string[]
}

const hasPermission = (data: PermissionData, permission: string): boolean => {
  return !data.permissions || data.permissions.includes(permission)
}

export const getEditPermissions = (data: PermissionData) => hasPermission(data, 'edit')
export const getDeletePermissions = (data: PermissionData) => hasPermission(data, 'delete')
export const getHiddenPermissions = (data: PermissionData) => hasPermission(data, 'hidden')
