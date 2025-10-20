/**
 * Author: Vika Aqordi
 * Created on 20-10-2025-13h-50m
 * Github: https://github.com/aqordeon
 * Copyright: 2025
*/

export default {
	blueprint: [
		{
			name: "Custom Top Navigation",
			key: ["custom_top", "properties"],
			// information: "This section is for customizing the top navigation bar of your website. You can adjust the background, border, shadow, and text properties to match your brand's style.",
			replaceForm: [
				{
					key: ["background"],
					label: "Background",
					type: "background",
				},
				{
					key: ["border"],
					label: "Border",
					type: "border",
				},
				{
					key: ["text"],
					label: "Text",
					type: "textProperty",
				},
			],
		},
		{
			name: "Custom Bottom Navigation",
			key: ["custom_bottom", "properties"],
			// information: "This section is for customizing the bottom navigation bar of your website. You can adjust the background, border, shadow, and text properties to match your brand's style.",
			replaceForm: [
				{
					key: ["background"],
					label: "Background",
					type: "background",
				},
				{
					key: ["border"],
					label: "Border",
					type: "border",
				},
				{
					key: ["text"],
					label: "Text",
					type: "textProperty",
				},
			],
		},
	],
}
