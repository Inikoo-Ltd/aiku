import { trans } from "laravel-vue-i18n"

export default {
	blueprint: [
		{
			name: "Container",
			icon: { icon: "fal fa-rectangle-wide", tooltip: "Container" },
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
					useIn: ["desktop", "tablet", "mobile"],
				},
				{
					key: ["margin"],
					label: "Margin",
					type: "margin",
					useIn: ["desktop", "tablet", "mobile"],
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
					key: ["image", "source"],
					label: "Upload image",
					type: "upload_image",
				},
				{
					key: ["link"],
					label: "Link",
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
					useIn: ["desktop", "tablet"],
				},
				{
					key: ["properties", "margin"],
					label: "Margin",
					type: "margin",
					useIn: ["desktop"],
				},
				{
					key: ["properties", "padding"],
					label: "Padding",
					type: "padding",
					useIn: ["desktop", "tablet", "mobile"],
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
			name: "Button 1",
			key: ["button_1"],
			icon: {
				icon: "fal fa-sign-in-alt",
				tooltip: "Action",
			},
			replaceForm: [
				{
					key: ["visible"],
					label: "Visible",
					type: "VisibleLoggedIn",
				},
				{
					key: ["container", "properties", "background"],
					label: "Background",
					type: "background",
				},
				{
					key: ["link"],
					label: "Link",
					type: "link",
				},
				{
					key: ["text"],
					label: "Text",
					type: "text",
				},
				{
					key: ["container", "properties", "text"],
					type: "textProperty",
				},
				{
					key: ["container", "properties", "margin"],
					label: "Margin",
					type: "margin",
					useIn: ["desktop", "tablet", "mobile"],
				},
				{
					key: ["container", "properties", "padding"],
					label: "Padding",
					type: "padding",
					useIn: ["desktop", "tablet", "mobile"],
				},
				{
					key: ["container", "properties", "border"],
					label: "Border",
					type: "border",
					useIn: ["desktop", "tablet", "mobile"],
				},
				{
					key: ["container", "properties", "dimension"],
					label: "Dimension",
					type: "dimension",
					useIn: ["desktop", "tablet", "mobile"],
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
