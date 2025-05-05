import { trans } from "laravel-vue-i18n"

export default {
	blueprint: [
		{
			name: "Container",
			icon: {
				icon: "fal fa-rectangle-wide",
				tooltip: "Container",
			},
			key: ["container", "properties"],
			replaceForm: [
				{
					key: ["background"],
					label: "Background",
					type: "background",
				},
				/*   {
                    key: ["text"],
                    label: "Text",
                    type: "textProperty"
                } */
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
					key: ["image","attributes", "fetchpriority"],
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
					key: [],
					label: "Button",
					type: "button",
				},
			],
		},
	],
}
