import { trans } from "laravel-vue-i18n"

export default {
	blueprint: [
		{
			key: ["container", "properties"],
			name: "Body",
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
			key: ["phone"],
			name: "Phone",
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
			key: ["email"],
			name: "Email",
			type: "text",
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
					key: ["properties", "margin"],
					label: "Margin",
					type: "margin",
				},
				{
					key: ["properties", "padding"],
					label: "Padding",
					type: "padding",
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
			key: ["socialMedia"],
			name: "Social Media",
			type: "socialMedia",
		},
	],
}
