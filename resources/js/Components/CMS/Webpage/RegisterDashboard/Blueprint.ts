import { library } from "@fortawesome/fontawesome-svg-core"
import { faAward, faBadgeCheck, faBox, faBoxOpen, faEnvelope, faPercent, faShippingFast, faStore, faTag, faTruck, faUsers } from "@fal"

library.add(faAward, faBadgeCheck, faBox, faBoxOpen, faEnvelope, faPercent, faShippingFast, faStore, faTag, faTruck, faUsers)

const iconPickerProps = {
	iconList: [faBox, faTag, faUsers, faPercent, faTruck, faStore, faEnvelope],
	listType: "custom",
}

const benefitBlueprint = [
	{
		key: ["text"],
		label: "Text",
		type: "editorhtml",
	},
	{
		key: ["icon"],
		label: "Icon",
		type: "icon-picker",
		props_data: iconPickerProps,
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
					aspectRatio: [4 / 3],
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
			text: "New benefit",
			icon: ["fal", "box"],
		},
	},
}

const signupGroup = {
	panel: "signup",
	name: "Sign up",
	key: ["signup"],
	replaceForm: [
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
	],
}

const registerButtonGroup = {
	panel: "register-button",
	name: "Register button",
	key: ["signup", "button"],
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
			key: ["icon"],
			label: "Icon",
			type: "icon-picker",
			props_data: iconPickerProps,
		},
		{
			key: ["link"],
			label: "Link",
			type: "link",
		},
	],
}

const googleGroup = {
	panel: "google",
	name: "Register with Google",
	key: ["signup", "google"],
	information: "Shown only when the website is served with a Google client id.",
	replaceForm: [
		{
			key: ["show"],
			label: "Show register with Google",
			type: "switch",
			props_data: {},
		},
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

const loginNoteGroup = {
	panel: "login-note",
	name: "Login note",
	key: ["signup", "login_note"],
	label: "Note",
	type: "editorhtml",
}

const whatsappGroup = {
	panel: "whatsapp",
	name: "WhatsApp",
	key: ["signup", "whatsapp"],
	replaceForm: [
		{
			key: ["show"],
			label: "Show WhatsApp note",
			type: "switch",
			props_data: {},
		},
		{
			key: ["title"],
			label: "Title",
			type: "editorhtml",
		},
		{
			key: ["text"],
			label: "Text",
			type: "editorhtml",
		},
		{
			key: ["note"],
			label: "Small print",
			type: "editorhtml",
		},
		{
			key: ["number"],
			label: "WhatsApp number",
			type: "text",
			information: "Number with country code, e.g. +44 7700 900123. The note opens a WhatsApp chat with it; leave empty to show the note without a link.",
		},
		{
			key: ["message"],
			label: "WhatsApp message",
			type: "text",
			information: "Optional text already typed in the chat when it opens.",
		},
	],
}

const audienceGroup = {
	panel: "audience",
	name: "Audience",
	key: ["signup", "audience"],
	replaceForm: [
		{
			key: ["show"],
			label: "Show audience line",
			type: "switch",
			props_data: {},
		},
		{
			key: ["text"],
			label: "Text",
			type: "editorhtml",
		},
		{
			key: ["icon"],
			label: "Icon",
			type: "icon-picker",
			props_data: iconPickerProps,
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
	{
		label: "Accent colour",
		key: ["accent_color"],
		type: "color",
		information: "Used by the register button, the outlined log in button and the links. Leave empty to use the website theme colour.",
	},
	heroGroup,
	benefitsGroup,
	signupGroup,
	registerButtonGroup,
	googleGroup,
	loginNoteGroup,
	whatsappGroup,
	audienceGroup,
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
