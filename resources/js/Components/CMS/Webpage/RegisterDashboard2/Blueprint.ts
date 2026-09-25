const benefitBlueprint = [
	{
		key: ["title"],
		label: "Title",
		type: "editorhtml",
	},
	{
		key: ["text"],
		label: "Description",
		type: "editorhtml",
	},
]

import { library } from "@fortawesome/fontawesome-svg-core"
import { faBadgeCheck, faCheck, faCheckCircle, faStar } from "@fal"

library.add(faBadgeCheck, faCheck, faCheckCircle, faStar)

const checkBlueprint = [
	{
		key: ["text"],
		label: "Text",
		type: "editorhtml",
	},
	{
		key: ["icon"],
		label: "Icon",
		type: "icon-picker",
		props_data: {
			iconList: [faCheck, faCheckCircle, faBadgeCheck, faStar],
			listType: "custom",
		},
	},
]

const faqBlueprint = [
	{
		key: ["question"],
		label: "Question",
		type: "editorhtml",
	},
	{
		key: ["answer"],
		label: "Answer",
		type: "editorhtml",
	},
]

const heroGroup = {
	panel: "hero",
	name: "Hero",
	key: ["hero"],
	replaceForm: [
		{
			key: ["eyebrow"],
			label: "Eyebrow",
			type: "editorhtml",
		},
		{
			key: ["title"],
			label: "Title",
			type: "editorhtml",
		},
		{
			key: ["intro"],
			label: "Intro",
			type: "editorhtml",
		},
		{
			key: ["image"],
			label: "Photo",
			type: "image-cropped",
			props_data: {
				stencilProps: {
					aspectRatio: [16 / 9],
					movable: true,
					scalable: true,
					resizable: true,
				},
			},
		},
		{
			key: ["image_url"],
			label: "Photo address",
			type: "text",
			information: "Address of an image already hosted elsewhere. Used when no photo is uploaded.",
		},
		{
			key: ["image_alt"],
			label: "Photo alternate text",
			type: "text",
		},
		{
			key: ["show_benefits"],
			label: "Show benefits",
			type: "switch",
			props_data: {},
		},
		{
			key: ["text_color"],
			label: "Text colour",
			type: "color",
		},
		{
			key: ["overlay_color"],
			label: "Photo shade",
			type: "color",
			information: "Colour of the gradient laid over the photo so its text stays readable.",
		},
		{
			key: ["benefit_border_color"],
			label: "Benefit lines",
			type: "color",
			information: "Lines on the left and right of each benefit.",
		},
	],
}

const benefitsGroup = {
	panel: "benefits",
	name: "Benefits",
	key: ["hero", "benefits"],
	type: "array-data",
	props_data: {
		blueprint: benefitBlueprint,
		order_name: "benefit",
		can_drag: true,
		can_delete: true,
		can_add: true,
		new_value_data: {
			title: "New benefit",
			text: null,
		},
	},
}

const cardGroup = {
	panel: "card",
	name: "Sign up card",
	key: ["card"],
	replaceForm: [
		{
			key: ["title"],
			label: "Title",
			type: "editorhtml",
		},
		{
			key: ["intro"],
			label: "Intro",
			type: "editorhtml",
		},
		{
			key: ["show_checks"],
			label: "Show check list",
			type: "switch",
			props_data: {},
		},
		{
			key: ["login_note"],
			label: "Login note",
			type: "editorhtml",
		},
		{
			key: ["help_note"],
			label: "Help note",
			type: "editorhtml",
		},
		{
			key: ["background_color"],
			label: "Card background",
			type: "color",
		},
		{
			key: ["area_background_color"],
			label: "Area background",
			type: "color",
			information: "Background around the card.",
		},
		{
			key: ["text_color"],
			label: "Text colour",
			type: "color",
		},
		{
			key: ["muted_color"],
			label: "Muted text colour",
			type: "color",
		},
		{
			key: ["line_color"],
			label: "Line colour",
			type: "color",
		},
	],
}

const checksGroup = {
	panel: "checks",
	name: "Check list",
	key: ["card", "checks"],
	type: "array-data",
	props_data: {
		blueprint: checkBlueprint,
		order_name: "check",
		can_drag: true,
		can_delete: true,
		can_add: true,
		new_value_data: {
			text: "New point",
			icon: ["fal", "check"],
		},
	},
}

const registerButtonGroup = {
	panel: "register-button",
	name: "Register button",
	key: ["card", "button"],
	replaceForm: [
		{
			key: ["show"],
			label: "Show register button",
			type: "switch",
			props_data: {},
		},
		{
			key: ["label"],
			label: "Label",
			type: "editorhtml",
		},
		{
			key: ["note"],
			label: "Note under the button",
			type: "editorhtml",
		},
		{
			key: ["link"],
			label: "Link",
			type: "link",
		},
		{
			key: ["color"],
			label: "Button colour",
			type: "color",
			information: "Also used for the check icons. Leave empty to use the website theme colour.",
		},
		{
			key: ["hover_color"],
			label: "Button hover colour",
			type: "color",
			information: "Also used for the links in the card.",
		},
	],
}

const googleGroup = {
	panel: "google",
	name: "Register with Google",
	key: ["card", "google"],
	information: "Shown only when the website is served with a Google client id.",
	replaceForm: [
	/* 	{
			key: ["show"],
			label: "Show register with Google",
			type: "switch",
			props_data: {},
		}, */
		{
			key: ["note"],
			label: "Note",
			type: "editorhtml",
		},
		{
			key: ["label"],
			label: "Button label",
			type: "editorhtml",
		},
	],
}

const faqGroup = {
	panel: "faq",
	name: "FAQ",
	key: ["faq"],
	replaceForm: [
		{
			key: ["show"],
			label: "Show FAQ",
			type: "switch",
			props_data: {},
		},
		{
			key: ["title"],
			label: "Title",
			type: "editorhtml",
		},
		{
			key: ["subtitle"],
			label: "Subtitle",
			type: "editorhtml",
		},
		{
			key: ["text_color"],
			label: "Text colour",
			type: "color",
		},
		{
			key: ["muted_color"],
			label: "Answer colour",
			type: "color",
		},
		{
			key: ["line_color"],
			label: "Line colour",
			type: "color",
		},
		{
			key: ["toggle_color"],
			label: "Toggle and link colour",
			type: "color",
		},
	],
}

const faqItemsGroup = {
	panel: "faq-items",
	name: "FAQ questions",
	key: ["faq", "items"],
	type: "array-data",
	props_data: {
		blueprint: faqBlueprint,
		order_name: "question",
		can_drag: true,
		can_delete: true,
		can_add: true,
		new_value_data: {
			question: "New question",
			answer: null,
		},
	},
}

const blueprint = [
	{
		label: "# Id ",
		key: ["id"],
		type: "text",
		information: "id selector is used to select one unique element!",
	},
	heroGroup,
	benefitsGroup,
	cardGroup,
	checksGroup,
	registerButtonGroup,
	googleGroup,
	faqGroup,
	faqItemsGroup,
	{
		panel: "layout",
		name: "Layout",
		key: ["container", "properties"],
		replaceForm: [
			{
				key: ["background"],
				label: "Background",
				type: "background",
				useIn: ["desktop", "tablet", "mobile"],
			},
			{
				key: ["padding"],
				label: "Padding",
				type: "padding",
				props_data: {},
				useIn: ["desktop", "tablet", "mobile"],
			},
			{
				key: ["margin"],
				label: "Margin",
				type: "margin",
				props_data: {},
				useIn: ["desktop", "tablet", "mobile"],
			},
		],
	},
]

/**
 * Side editor panel opened for each part of the block, keyed by the data-rd-panel name the block
 * puts on that part. The value is the accordion key the side editor gives the panel.
 */
export const panelKeys: Record<string, string> = Object.fromEntries(
	blueprint.filter(group => "panel" in group).map(group => [group.panel, group.key.join("-")])
)

export default {
	blueprint,
}
