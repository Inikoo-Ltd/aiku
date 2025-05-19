import { trans } from "laravel-vue-i18n"

export default {
	blueprint: [
		{
			name: "Logo",
			key: ["logo"],
			icon: {
				icon: "fal fa-image",
				tooltip: "Logo",
			},
			replaceForm: [
				{
					key: ["image", "source"],
					label: "Upload image",
					type: "upload_image",
				},
				{
					key: ["link"],
					label : "Link",
					type: "link",
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
					useIn : ["desktop", "tablet", "mobile"],
				},
				{
					key: ["properties", "margin"],
					label: "Margin",
					type: "margin",
					useIn : ["desktop", "tablet", "mobile"],
				},
				{
					key: ["properties", "padding"],
					label: "Padding",
					type: "padding",
					useIn : ["desktop", "tablet", "mobile"],
				},
				{
					key: ["image", "attributes", "fetchpriority"],
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
			name: "Mobile",
			key: ["mobile"],
			icon: {
				icon: "fal fa-mobile",
				tooltip: "Action",
			},
			replaceForm: [
				{
					key: ["profile"],
					name: "Profile Icon",
					replaceForm: [
						{
							key: ["icon"],
							label: "Icon",
							type: "icon-picker",
						},
						{
							key: ["container", "properties", "text"],
							label: "Icon Setting",
							type: "textProperty",
						},
					],
				},
				{
					key: ["menu"],
					name: "Menu Icon",
					replaceForm: [
						{
							key: ["icon"],
							label: "Icon",
							type: "icon-picker",
						},
						{
							key: ["container", "properties", "text"],
							label: "Icon Setting",
							type: "textProperty",
						},
					],
				},
			],
		},
	],
}
