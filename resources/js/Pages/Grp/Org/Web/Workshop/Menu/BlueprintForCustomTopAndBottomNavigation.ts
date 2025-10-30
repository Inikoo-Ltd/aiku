/**
 * Author: Vika Aqordi
 * Created on 20-10-2025-13h-50m
 * Github: https://github.com/aqordeon
 * Copyright: 2025
*/

import { trans } from "laravel-vue-i18n"

export default {
	blueprint: [
		{
			name: "Styling Navigation 1",
			key: ["custom_navigation_1_styling"],
			// information: "This section is for customizing the top navigation bar of your website. You can adjust the background, border, shadow, and text properties to match your brand's style.",
			replaceForm: [
				{
					key: ["index_of_navigation_to_apply"],
					label: "Navigation to apply",
					information: trans('Specify the index numbers of the navigation items you want to apply the custom styling to. Separate multiple indexes with commas i.e 1, 4, 5, 9'),
					props_data: {
						placeholder: "i.e 1, 2, 5, 7, 8",
					},
					type: "text",
				},
				{
					key: ["properties", "background"],
					label: "Background",
					type: "background",
				},
				{
					key: ["properties", "border"],
					label: "Border",
					type: "border",
				},
				{
					key: ["properties", "text"],
					label: "Text",
					type: "textProperty",
				},
			],
		},
		{
			name: "Styling Navigation 2",
			key: ["custom_navigation_2_styling"],
			// information: "This section is for customizing the top navigation bar of your website. You can adjust the background, border, shadow, and text properties to match your brand's style.",
			replaceForm: [
				{
					key: ["index_of_navigation_to_apply"],
					label: "Navigation to apply",
					information: trans('Specify the index numbers of the navigation items you want to apply the custom styling to. Separate multiple indexes with commas i.e 1, 4, 5, 9'),
					props_data: {
						placeholder: "i.e 1, 2, 5, 7, 8",
					},
					type: "text",
				},
				{
					key: ["properties", "background"],
					label: "Background",
					type: "background",
				},
				{
					key: ["properties", "border"],
					label: "Border",
					type: "border",
				},
				{
					key: ["properties", "text"],
					label: "Text",
					type: "textProperty",
				},
			],
		},
	],
}
