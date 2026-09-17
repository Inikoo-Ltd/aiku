import { faBox, faEnvelope, faPercent, faStore, faTag, faTruck, faUsers } from "@fal"

const iconPickerProps = {
	iconList: [faBox, faTag, faUsers, faPercent, faTruck, faStore, faEnvelope],
	listType: "custom",
}

const benefitBlueprint = [
	{
		key: ["text"],
		label: "Text",
		type: "text",
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
		type: "text",
	},
	{
		key: ["answer"],
		label: "Answer",
		type: "editorhtml",
	},
]

const footerLinkBlueprint = [
	{
		key: ["label"],
		label: "Label",
		type: "text",
	},
	{
		key: ["link"],
		label: "Link",
		type: "link",
	},
]

const headerGroup = {
	name: "Header",
	key: ["header"],
	information: "A slim header for the registration page. Hide it when the website already shows its own header above the block.",
	replaceForm: [
		{
			key: ["show"],
			label: "Show header",
			type: "switch",
			props_data: {},
		},
		{
			key: ["logo"],
			label: "Logo",
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
			key: ["logo_url"],
			label: "Logo address",
			type: "text",
			information: "Address of an image already hosted elsewhere. Used when no logo is uploaded.",
		},
		{
			key: ["logo_alt"],
			label: "Logo alternate text",
			type: "text",
		},
		{
			key: ["home", "label"],
			label: "Back to shop label",
			type: "text",
		},
		{
			key: ["home", "link"],
			label: "Back to shop link",
			type: "link",
		},
		{
			key: ["login", "label"],
			label: "Log in label",
			type: "text",
		},
		{
			key: ["login", "link"],
			label: "Log in link",
			type: "link",
		},
	],
}

const heroGroup = {
	name: "Hero",
	key: ["hero"],
	replaceForm: [
		{
			key: ["title"],
			label: "Title",
			type: "text",
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
		{
			key: ["benefits"],
			name: "Benefits",
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
		},
	],
}

const signupGroup = {
	name: "Sign up",
	key: ["signup"],
	replaceForm: [
		{
			key: ["title"],
			label: "Title",
			type: "text",
		},
		{
			key: ["subtitle"],
			label: "Subtitle",
			type: "text",
		},
		{
			key: ["button", "show"],
			label: "Show register button",
			type: "switch",
			props_data: {},
		},
		{
			key: ["button", "label"],
			label: "Register button label",
			type: "text",
		},
		{
			key: ["button", "icon"],
			label: "Register button icon",
			type: "icon-picker",
			props_data: iconPickerProps,
		},
		{
			key: ["button", "link"],
			label: "Register button link",
			type: "link",
		},
		{
			key: ["google", "show"],
			label: "Show register with Google",
			type: "switch",
			props_data: {},
			information: "Shown only when the website is served with a Google client id.",
		},
		{
			key: ["google", "note"],
			label: "Google note",
			type: "text",
		},
		{
			key: ["google", "label"],
			label: "Google button label",
			type: "text",
		},
		{
			key: ["login_note"],
			label: "Login note",
			type: "editorhtml",
		},
		{
			key: ["whatsapp", "show"],
			label: "Show WhatsApp note",
			type: "switch",
			props_data: {},
		},
		{
			key: ["whatsapp", "title"],
			label: "WhatsApp title",
			type: "text",
		},
		{
			key: ["whatsapp", "text"],
			label: "WhatsApp text",
			type: "text",
		},
		{
			key: ["whatsapp", "note"],
			label: "WhatsApp small print",
			type: "text",
		},
		{
			key: ["audience", "show"],
			label: "Show audience line",
			type: "switch",
			props_data: {},
		},
		{
			key: ["audience", "text"],
			label: "Audience text",
			type: "text",
		},
		{
			key: ["audience", "icon"],
			label: "Audience icon",
			type: "icon-picker",
			props_data: iconPickerProps,
		},
	],
}

const faqGroup = {
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
			type: "text",
		},
		{
			key: ["items"],
			name: "Questions",
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
		},
	],
}

const footerGroup = {
	name: "Footer",
	key: ["footer"],
	information: "A slim footer for the registration page. Hide it when the website already shows its own footer under the block.",
	replaceForm: [
		{
			key: ["show"],
			label: "Show footer",
			type: "switch",
			props_data: {},
		},
		{
			key: ["text"],
			label: "Text",
			type: "editorhtml",
		},
		{
			key: ["email"],
			label: "Email",
			type: "text",
		},
		{
			key: ["links"],
			name: "Links",
			type: "array-data",
			props_data: {
				blueprint: footerLinkBlueprint,
				order_name: "link",
				can_drag: true,
				can_delete: true,
				can_add: true,
				new_value_data: {
					label: "New link",
					link: null,
				},
			},
		},
	],
}

export default {
	blueprint: [
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
		signupGroup,
		faqGroup,
		headerGroup,
		footerGroup,
		{
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
	],
}
