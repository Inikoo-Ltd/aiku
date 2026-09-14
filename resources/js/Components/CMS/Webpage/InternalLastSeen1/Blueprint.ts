import { ctrans } from "@/Composables/useTrans"

export const blueprint = (data?: {}) => {
	return {
		blueprint: [
			{
				label: "# Id ",
				key: ["id"],
				type: "text",
				information: "id selector is used to select one unique element!",
			},
			{
				name: "Settings",
				key: ["settings"],
				replaceForm: [
					{
						key: ["per_row"],
						label: ctrans("Number slides per row"),
						type: "number",
						useIn: ["desktop", "tablet", "mobile"],
						information: ctrans("Can use decimal e.g. 4.5 to show half of next slide"),
					},
				],
			},
		],
	}
}

export default blueprint
