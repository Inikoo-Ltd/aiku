import { trans } from "laravel-vue-i18n"
import { faRectangleLandscape, faPhone, faEnvelope, faShare } from "@fal"
import { faRss } from "@far"
import { faWhatsapp } from "@fortawesome/free-brands-svg-icons"

export default {
	blueprint: [
		{
			key: ["container", "properties"],
			name: "Body",
			icon: {
				icon: faRectangleLandscape,
				tooltip: "Body",
			},
			replaceForm: [
				{
					key: ["background"],
					label: "Background",
					type: "background",
				},
				{
					key: ["text"],
					label: "Text",
					type: "textProperty",
				},
			],
		},
		{
			name: "Logo",
			key: ["logo"],
			icon: {
				icon: "fal fa-image",
				tooltip: "Logo",
			},
			replaceForm: [
				{
					key: ["source"],
					label: "Upload image",
					type: "upload_image",
				},
				{
					key: ["alt"],
					label: "Alternate Text",
					type: "text",
				},
				{
					key: ["properties", "dimension"],
					label: "Dimension",
					type: "dimension",
				},
				{
					key: ["attributes", "fetchpriority"],
					label: trans("Fetch Priority"),
					information: trans(
						"Priority of the image to loaded. Higher priority images are loaded first (good for LCP)."
					),
					type: "select",
					props_data: {
						placeholder: trans("Priority"),
						options: [
							{
								label: trans("High"),
								value: "high",
							},
							{
								label: trans("Low"),
								value: "low",
							},
						],
					},
				},
			],
		},
		{
			key: ["phone"],
			name: "Phone",
			icon: {
				icon: faPhone,
				tooltip: "Phone",
			},
			replaceForm: [
				{
					key: ["numbers"],
					label: "Phone",
					type: "arrayPhone",
				},
				{
					key: ["caption"],
					label: "Caption",
					type: "text",
				},
			],
		},
		{
			key: ["whatsapp"],
			name: "Whatsapp",
			icon: {
				icon: faWhatsapp,
				tooltip: "Whatsapp",
			},
			replaceForm: [
				{
					key: ["number"],
					label: "Phone",
					type: "text",
				},
				{
					key: ["caption"],
					label: "Caption",
					type: "text",
				},
				{
					key: ["message"],
					label: "Message",
					type: "text",
				},
			],
		},
		{
			icon: {
				icon: faEnvelope,
				tooltip: "Email",
			},
			key: ["email"],
			name: "Email",
			type: "text",
		},	
		{
			icon: {
				icon: faShare,
				tooltip: "Social Media",
			},
			key: ["socialMedia"],
			name: "Social Media",
			type: "socialMedia",
		},
		{
			icon: {
				icon: faEnvelope,
				tooltip: "Email",
			},
			key: ["paymentData","data"],
			name: "Payment",
			type: "payment_templates",
		},
		{
			key: ["subscribe"],
			name: "Newsletter Subscribe",
			show_new_until: "2025-07-04",
			icon: {
				icon: faRss,
				tooltip: "Newsletter Subscribe",
			},
			replaceForm: [
				{
					key: ["is_show"],
					label: "Show in footer?",
					// props_data: {
					// 	placeholder: "Enter your email",
					// },
					// reset_value: {
					// 	value: "Enter your email",
					// },
					// information: "The text that will be displayed when the input box empty.",
					type: "switch",
				},
				{
					key: ["placeholder"],
					label: "Placeholder",
					props_data: {
						placeholder: "Enter your email",
					},
					reset_value: {
						value: "Enter your email",
					},
					information: "The text that will be displayed when the input box empty.",
					type: "text",
				},
				{
					key: ["headline"],
					label: "Headline",
					props_data: {
						placeholder: "Subscribe to our newsletter",
					},
					reset_value: {
						value: "Subscribe to our newsletter",
						is_refresh_field_on_reset: true,
					},
					type: "editorhtml",
				},
				{
					key: ["description"],
					label: "Description",
					reset_value: {
						value: "The latest news, articles, and resources, sent to your inbox weekly.",
						is_refresh_field_on_reset: true,
					},
					type: "editorhtml",
				},
			],
		},
	],
}
