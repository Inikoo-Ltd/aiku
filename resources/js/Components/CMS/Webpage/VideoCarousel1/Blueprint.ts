import VideoBlueprint from "./VideoBlueprint"

export default {
	blueprint: [
		{
			label: "# Id ",
			key: ["id"],
			type: "text",
			information: "id selector is used to select one unique element!",
		},
		{
			label: "Responsive Visibility",
			key: ["container", "properties", "visibility"],
			type: "visibility",
			useIn: ["desktop", "tablet", "mobile"],
		},
		{
			key: ["videos"],
			name: "Videos",
			type: "array-data-drawer",
			props_data: {
				blueprint: VideoBlueprint.blueprint,
				order_name: "video",
				can_drag: true,
				can_delete: true,
				can_add: true,
				new_value_data: {
					source: null,
					link: null,
					link_label: null,
				},
			},
		},
		{
			name: "Carousel Settings",
			key: ["settings"],
			replaceForm: [
				{
					label: "Videos visible at once",
					key: ["slidesPerView"],
					useIn: ["desktop", "tablet", "mobile"],
					type: "number",
				},
				{
					label: "Space Between",
					key: ["spaceBetween"],
					type: "number",
				},
				{
					label: "Autoplay (muted)",
					key: ["autoplay"],
					type: "switch",
				},
				{
					label: "Loop",
					key: ["loop"],
					type: "switch",
				},
				{
					label: "Arrows",
					key: ["arrows"],
					type: "switch",
				},
			],
		},
		{
			name: "Tiles",
			key: ["tile", "properties"],
			replaceForm: [
				{
					key: ["background"],
					label: "Background",
					type: "background",
				},
				{
					key: ["border"],
					label: "Border and corners",
					type: "border",
					useIn: ["desktop", "tablet", "mobile"],
				},
				{
					key: ["shadow"],
					label: "Shadow",
					type: "shadow",
					useIn: ["desktop", "tablet", "mobile"],
				},
			],
		},
		{
			name: "Button",
			key: ["button"],
			editGlobalStyle: "button",
			replaceForm: [
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
					key: ["show"],
					label: "Show Button",
					type: "switch",
				},
				{
					key: ["container", "properties", "text"],
					type: "textProperty",
					useIn: ["desktop", "tablet", "mobile"],
				},
				{
					key: ["container", "properties", "background"],
					label: "Background",
					type: "background",
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
			],
		},
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
					useIn: ["desktop", "tablet", "mobile"],
				},
				{
					key: ["margin"],
					label: "Margin",
					type: "margin",
					useIn: ["desktop", "tablet", "mobile"],
				},
				{
					key: ["border"],
					label: "Border",
					type: "border",
					useIn: ["desktop", "tablet", "mobile"],
				},
			],
		},
	],
}
