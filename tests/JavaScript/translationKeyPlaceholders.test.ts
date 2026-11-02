import assert from "node:assert/strict"
import { readFileSync, readdirSync } from "node:fs"
import { join } from "node:path"
import test from "node:test"

const root = join(import.meta.dirname, "..", "..")
const langDirectory = join(root, "lang")
const dimensionKey = "This will overwrite :count products dimensions, are you sure want to continue ?"

const filesUnder = (directory: string): string[] =>
	readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
		const path = join(directory, entry.name)
		if (entry.isDirectory()) {
			return filesUnder(path)
		}
		return /\.(vue|ts|js)$/.test(entry.name) ? [path] : []
	})

const languageFiles = readdirSync(langDirectory).filter((name) => name.endsWith(".json"))

test("no live ctrans call builds its key from an interpolated template literal", () => {
	const offenders = filesUnder(join(root, "resources", "js")).flatMap((path) =>
		readFileSync(path, "utf8")
			.split("\n")
			.filter((line) => !line.trim().startsWith("//") && /ctrans\(`[^`]*\$\{/.test(line))
			.map((line) => `${path}: ${line.trim()}`)
	)

	assert.deepEqual(offenders, [])
})

test("the update-dimensions confirmation is looked up by a :count key in every language that translates it", () => {
	const translated = languageFiles.filter((name) => {
		const strings = JSON.parse(readFileSync(join(langDirectory, name), "utf8"))
		assert.ok(
			!Object.keys(strings).some((key) => key.includes("${totalProductsForDimensionUpdate}")),
			`${name} still carries the interpolated key`
		)
		return dimensionKey in strings
	})

	assert.ok(translated.includes("pl.json"))
	assert.ok(translated.includes("en.json"))

	for (const name of translated) {
		const value = JSON.parse(readFileSync(join(langDirectory, name), "utf8"))[dimensionKey]
		assert.equal(value.match(/:count\b/g)?.length, 1, `${name} must keep :count exactly once`)
	}
})

test("the update-dimensions confirmation survives t:up through the glossary of every language that translates it", () => {
	const glossary = JSON.parse(readFileSync(join(root, "resources", "translation-glossary.json"), "utf8"))

	for (const name of languageFiles.filter((name) => name !== "en.json" && !name.startsWith("php_") && name !== "texts_to_translate.json")) {
		const locale = name.replace(/\.json$/, "")
		const strings = JSON.parse(readFileSync(join(langDirectory, name), "utf8"))
		if (dimensionKey in strings) {
			assert.equal(glossary[locale]?.[dimensionKey], strings[dimensionKey], `${locale} glossary`)
		}
	}
})

test("the Retina basket recommendations translate through ctrans, not a trans alias", () => {
	const source = readFileSync(
		join(root, "resources", "js", "Components", "Retina", "BasketRecommendationsInternal.vue"),
		"utf8"
	)

	assert.match(source, /import \{ ctrans \} from '@\/Composables\/useTrans'/)
	assert.doesNotMatch(source, /ctrans as trans/)
	assert.doesNotMatch(source, /(?<![\w.])trans\(/)
})

const fullyTranslatedLocales = ["sk", "es"]

test("every fully translated language keeps the placeholders of its key", () => {
	const placeholders = (text: string) => [...new Set(text.match(/(?<![\w/]):[A-Za-z_][A-Za-z0-9_]*/g) ?? [])].sort()

	for (const locale of fullyTranslatedLocales) {
		const strings: Record<string, string> = JSON.parse(readFileSync(join(langDirectory, `${locale}.json`), "utf8"))
		const offenders = Object.entries(strings)
			.filter(([key, value]) => placeholders(key).join() !== placeholders(value).join())
			.map(([key]) => key)

		assert.deepEqual(offenders, [], locale)
	}
})

test("every Spanish question and exclamation opens with its inverted mark", () => {
	const spanish: Record<string, string> = JSON.parse(readFileSync(join(langDirectory, "es.json"), "utf8"))
	const offenders = Object.entries(spanish)
		.filter(([key, value]) => {
			const text = value.replace(/https?:\/\/\S+/g, "").replace(/<[^>]*>/g, "")
			return (text.includes("?") && !text.includes("¿")) || (key.includes("!") && text.includes("!") && !text.includes("¡"))
		})
		.map(([key]) => key)

	assert.deepEqual(offenders, [])
})

test("a count is never glued to a bare translated word", () => {
	const countWords = ["touches", "chats", "labels", "units", "hours", "blocks", "lines selected", "stocks selected", "requests", "views", "others", "days out of stock", "new registrations", "d", "w", "d late"]
	const glued = new RegExp(`\\}\\}?\\s*(\\{\\{|\\$\\{)\\s*c?trans\\((["'])(${countWords.join("|")})\\2\\)`)
	const offenders = filesUnder(join(root, "resources", "js")).flatMap((path) =>
		readFileSync(path, "utf8")
			.split("\n")
			.filter((line) => glued.test(line) || /:packs\)['"], \{ packs: [^}]*\+ ['"]s['"]/.test(line))
			.map((line) => `${path}: ${line.trim()}`)
	)

	assert.deepEqual(offenders, [])
})

test("every plural key gives Slovak its three forms and falls back to English", () => {
	const english: Record<string, string> = JSON.parse(readFileSync(join(langDirectory, "en.json"), "utf8"))
	const slovak: Record<string, string> = JSON.parse(readFileSync(join(langDirectory, "sk.json"), "utf8"))
	const pluralKeys = Object.keys(english).filter((key) => /^(hour|[^|]*:(count|number|progress) [^|]*)\|[^| ][^|]*$/.test(key) && !key.includes(" | "))

	assert.ok(pluralKeys.includes(":count unit|:count units"))
	assert.ok(pluralKeys.includes("hour|hours"))
	assert.equal(slovak[":number passed"].split("|").length, 3)
	assert.deepEqual(pluralKeys.filter((key) => english[key] !== key || (key in slovak && slovak[key].split("|").length !== 3)), [])

	const placeholders = (text: string) => [...new Set(text.match(/:[a-z_]+/g) ?? [])].sort().join()
	const formsByLocale: Record<string, number> = { cs: 3, hr: 3, lv: 3, pl: 3, ro: 3, sk: 3, uk: 3 }

	for (const name of languageFiles.filter((name) => name !== "en.json" && !name.startsWith("php_") && name !== "texts_to_translate.json")) {
		const locale = name.replace(/\.json$/, "")
		const strings: Record<string, string> = JSON.parse(readFileSync(join(langDirectory, name), "utf8"))
		assert.ok(strings[":count unit|:count units"], name)
		const offenders = pluralKeys.filter((key) => key in strings).filter((key) => {
			const forms = strings[key].split("|")
			return forms[0] === "" || forms.length > (formsByLocale[locale] ?? 2) || forms.some((form) => placeholders(form) !== placeholders(key))
		})
		assert.deepEqual(offenders, [], name)
	}
})

test("a word with two meanings is looked up by a key of its own at each call site", () => {
	const separated: [string, RegExp, string][] = [
		["app/Http/Resources/SupplyChain/SuppliersResource.php", /__\('Free'\)/, "No agent"],
		["app/Actions/SupplyChain/Supplier/UI/IndexSuppliers.php", /__\('Free'\)/, "No agent"],
		["app/Actions/SupplyChain/SupplierProduct/UI/IndexSupplierProducts.php", /__\('Free'\)/, "No agent"],
		["resources/js/Components/Tables/Grp/SupplyChain/TableAssignableSuppliers.vue", /ctrans\("Free"\)/, "No agent"],
		["resources/js/Components/DevOps/ServerUsageCard.vue", /ctrans\("Free"\)/, "Free space"],
		["resources/js/Components/Chat/Agent/MessageAreaAgent.vue", /ctrans\("Off"\)/, "Switched off"],
		["app/Actions/Inventory/OrgStockFamily/UI/IndexOrgStockFamilies.php", /__\('Off'\)/, "Switched off"],
		["resources/js/Pages/Grp/Goods/ProductCommandControl.vue", /ctrans\("OFF"\)/, "OFF SALE"],
		["resources/js/Components/Tickets/TicketControls.vue", /ctrans\("Pass"\)/, "Pass QA"],
		["resources/js/Components/DevOps/CiRunCard.vue", /ctrans\("passed"\)/, ":number passed"],
		["app/Actions/UI/HumanResources/ShowHumanResourcesDashboard.php", /__\('Present'\)/, "Staff present"],
		["app/Actions/HumanResources/EmployeeContract/UI/IndexEmployeeContracts.php", /__\('Present'\)/, "Present day"],
		["app/Actions/Production/PartnerShippingList/UI/IndexPrePickList.php", /__\('Lines?'\)/, "lines"],
		["resources/js/Components/SalesAnalysis/SalesAnalysisReport.vue", /ctrans\("Lines"\)/, "Draw lines"],
		["app/Actions/Production/Restock/UI/ShowToRestock.php", /__\('Watch'\)/, "On watch"],
		["resources/js/Components/DevOps/TelemetryPanel.vue", /ctrans\("Jobs"\)/, "Queue jobs"],
		["resources/js/Components/Tables/Grp/Org/CRM/TableCustomerReorderProducts.vue", /ctrans\("Due"\)/, "Due now"],
		["resources/js/Pages/Grp/Org/HumanResources/LeaveAdmin.vue", /ctrans\('Record'\)/, "Record leave"],
		["resources/js/Components/Seo/SeoAiVisibilityOverview.vue", /ctrans\("Named"\)/, "Brand named"],
		["resources/js/Components/Seo/SeoAiPrompts.vue", /ctrans\("Named"\)/, "Brand named"],
		["resources/js/Components/Offers/ModalCreateStepDiscountProduct.vue", /ctrans\('Add step'\)/, "Add discount step"],
		["app/Actions/Web/WebsiteNotFoundPath/UI/IndexWebsiteNotFoundPaths.php", /__\('Hits'\)/, "404 visits"],
		["resources/js/Components/Seo/serpFeatures.ts", /ctrans\("Shopping"\)/, "Shopping results"],
		["resources/js/Components/Utils/Label/DiscountTemplate/CategoryQuantityOrderedOrderInterval/FamilyOfferLabelGRV2.vue", /ctrans\('(Gold|Reward)'\)/, "Gold Reward"],
		["app/Actions/CRM/TrafficSource/GetShopAttributionDataQuality.php", /__\('touches'\)/, ":count touch|:count touches"],
		["app/Actions/CRM/TrafficSourceCampaign/UI/ShowGoogleAdsCampaign.php", /__\('Review'\)/, "Review campaign"],
		["app/Actions/CRM/TrafficSourceCampaign/UI/ShowGoogleAdsCampaignReview.php", /__\('Review'\)/, "Review :name"],
		["app/Actions/Retina/Dropshipping/Orders/ShowRetinaDropshippingOrder.php", /__\('Review'\)/, "Review order"],
		["app/Actions/Retina/Ecom/Orders/ShowRetinaEcomOrder.php", /__\('Review'\)/, "Review order"],
		["resources/js/Components/Procurement/StockDeliveryInvoiceCosting.vue", /ctrans\('Review'\)/, "Review invoice"],
		["resources/js/Components/Chat/WhatsappCallBar.vue", /ctrans\("Answer"\)/, "Answer call"],
		["resources/js/Layouts/Grp/WhatsappCallAlert.vue", /ctrans\("Answer"\)/, "Answer call"],
		["app/Enums/Helpers/Ticket/TicketLinkTypeEnum.php", /__\('Blocks'\)/, "Is blocking"],
		["resources/js/Pages/Grp/Org/Production/PartnerShippingList.vue", /ctrans\("free"\)/, "not promised"],
		["resources/js/Components/Navigation/Breadcrumbs.vue", /ctrans\('Up'\)/, "Go up a level"],
		["resources/js/Components/Warehouse/Inventory/OrgStockDiscontinuePreviewModal.vue", /ctrans\("Open orders"\)/, "Open customer orders"],
		["resources/js/Pages/Grp/Org/HumanResources/DashboardLeave.vue", /ctrans\(\"Holiday\"\)/, "Public holiday"],
		["app/Exports/HumanResources/HolidaysExport.php", /__\('Holiday'\)/, "Public holiday"],
		["app/Actions/HumanResources/Leave/StoreLeave.php", /__\('Holiday'\)/, "Public holiday"],
		["app/Actions/HumanResources/Calendar/WithCalendarSubNavigation.php", /__\('Holiday'\)/, "Public holidays"],
		["resources/js/Components/Tables/Grp/Org/HumanResources/TableShiftSchedules.vue", /ctrans\(\"off\"\)/, "Day off"],
		["resources/js/Components/Tasks/StaffTaskQuickLook.vue", /ctrans\(\"About\"\)/, "Regarding"],
		["resources/js/Pages/Grp/Org/Procurement/AgentLedger.vue", /ctrans\('Record'\)/, "Record entry"],
		["app/Actions/Procurement/GetOrganisationStockCoverBuckets.php", /'label' => 'Watch'/, "On watch"],
		["app/Enums/Production/ManufactureArtisanDay/ManufactureArtisanDayStatusEnum.php", /__\('Over'\)/, "Over shift time"],
		["app/Enums/SupplyChain/PackingSpec/PackingSpecRoleEnum.php", /__\('Divider'\)/, "Carton divider"],
		["resources/js/Components/Tasks/StaffTaskControls.vue", /ctrans\([\"']Department[\"']\)/, "Team department"],
		["resources/js/Components/Tasks/StaffTaskFilters.vue", /ctrans\([\"']Department[\"']\)/, "Team department"],
		["resources/js/Pages/Grp/Tasks/StaffTasksBoard.vue", /ctrans\([\"']Department[\"']\)/, "Team department"],
		["resources/js/Components/HumanResources/JobPositionShowcase.vue", /ctrans\([\"']Department[\"']\)/, "Team department"],
		["resources/js/Pages/Grp/Org/HumanResources/LeaveAdmin.vue", /ctrans\([\"']Department[\"']\)/, "Team department"],
		["resources/js/Pages/Grp/Org/HumanResources/DashboardLeave.vue", /ctrans\([\"']Department[\"']\)/, "Team department"],
		["resources/js/Pages/Grp/Tasks/StaffTasksReports.vue", /ctrans\([\"']Departments?[\"']\)/, "Team department"],
		["app/Exports/HumanResources/LeavesExport.php", /__\('Department'\)/, "Team department"],
		["app/Exports/HumanResources/OvertimeRequestsExport.php", /__\('Department'\)/, "Team department"],
		["resources/views/mail/leave-approved.blade.php", /__\('Department'\)/, "Team department"],
		["app/Actions/CRM/Customer/UI/IndexCustomers.php", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]Location['"]/, "Customer location"],
		["app/Actions/CRM/Customer/UI/IndexCustomerClients.php", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]Location['"]/, "Customer location"],
		["app/Actions/Retina/Fulfilment/Dropshipping/Client/UI/IndexRetinaFulfilmentCustomerClientsInCustomerSalesChannel.php", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]Location['"]/, "Customer location"],
		["app/Actions/Retina/Dropshipping/Client/UI/IndexRetinaCustomerClients.php", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]Location['"]/, "Customer location"],
		["app/Actions/Fulfilment/FulfilmentCustomer/UI/IndexFulfilmentCustomersPendingApproval.php", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]Location['"]/, "Customer location"],
		["app/Actions/Billables/Charge/UI/IndexCustomersInCharge.php", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]Location['"]/, "Customer location"],
		["app/Actions/Discounts/OfferCampaign/UI/IndexCustomersInOfferTotal.php", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]Location['"]/, "Customer location"],
		["app/Actions/Discounts/OfferCampaign/UI/IndexCustomersInOffer.php", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]Location['"]/, "Customer location"],
		["app/Actions/Pupil/IndexPupilPlatformCustomerClients.php", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]Location['"]/, "Customer location"],
		["app/Actions/Dropshipping/Customers/UI/IndexCustomers.php", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]Location['"]/, "Customer location"],
		["app/Actions/CRM/Customer/UI/IndexCustomers.php", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]location['"]/, "Customer location"],
		["app/Actions/CRM/Customer/UI/IndexCustomerClients.php", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]location['"]/, "Customer location"],
		["app/Actions/Retina/Fulfilment/Dropshipping/Client/UI/IndexRetinaFulfilmentCustomerClientsInCustomerSalesChannel.php", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]location['"]/, "Customer location"],
		["app/Actions/Retina/Dropshipping/Client/UI/IndexRetinaCustomerClients.php", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]location['"]/, "Customer location"],
		["app/Actions/Fulfilment/FulfilmentCustomer/UI/IndexFulfilmentCustomersPendingApproval.php", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]location['"]/, "Customer location"],
		["app/Actions/Billables/Charge/UI/IndexCustomersInCharge.php", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]location['"]/, "Customer location"],
		["app/Actions/Discounts/OfferCampaign/UI/IndexCustomersInOfferTotal.php", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]location['"]/, "Customer location"],
		["app/Actions/Discounts/OfferCampaign/UI/IndexCustomersInOffer.php", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]location['"]/, "Customer location"],
		["app/Actions/Pupil/IndexPupilPlatformCustomerClients.php", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]location['"]/, "Customer location"],
		["app/Actions/Dropshipping/Customers/UI/IndexCustomers.php", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]location['"]/, "Customer location"],
		["app/Actions/SupplyChain/Agent/UI/IndexAgents.php", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]Location['"]/, "Supplier location"],
		["app/Actions/SupplyChain/Supplier/UI/IndexAgentSuppliers.php", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]Location['"]/, "Supplier location"],
		["app/Actions/SupplyChain/Supplier/UI/IndexSuppliers.php", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]Location['"]/, "Supplier location"],
		["app/Actions/SupplyChain/Supplier/UI/IndexAssignableSuppliers.php", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]Location['"]/, "Supplier location"],
		["app/Actions/Procurement/OrgSupplier/UI/IndexOrgSuppliers.php", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]Location['"]/, "Supplier location"],
		["app/Actions/Procurement/OrgSupplier/UI/CreateOrgSupplier.php", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]Location['"]/, "Supplier location"],
		["app/Actions/Procurement/OrgSupplier/UI/IndexOrgAgentSuppliers.php", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]Location['"]/, "Supplier location"],
		["app/Actions/Procurement/OrgAgent/UI/IndexSuspendedOrgAgents.php", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]Location['"]/, "Supplier location"],
		["app/Actions/Web/WebsiteVisitor/UI/IndexWebsiteVisitors.php", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]Location['"]/, "Visitor location"],
		["resources/js/Pages/Grp/Org/Web/ShowLiveUsers.vue", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]Location['"]/, "Visitor location"],
		["app/Actions/CRM/WebUser/UI/IndexWebUserLogins.php", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]Location['"]/, "Login location"],
		["app/Actions/CRM/WebUser/UI/IndexWebUserFailedLogins.php", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]Location['"]/, "Login location"],
		["app/Actions/SysAdmin/WebUserRequest/UI/Traits/WithWebUserRequestsUI.php", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]Location['"]/, "Login location"],
		["app/Actions/SysAdmin/UI/IndexUserRequests.php", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]Location['"]/, "Login location"],
		["app/Actions/HumanResources/Employee/UI/IndexEmployeeUserActivity.php", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]Location['"]/, "Login location"],
		["app/Actions/Retina/UI/SysAdmin/IndexRetinaWebUsers.php", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]Location['"]/, "Login location"],
		["app/Actions/CRM/WebUser/UI/IndexWebUserLogins.php", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]location['"]/, "Login location"],
		["app/Actions/CRM/WebUser/UI/IndexWebUserFailedLogins.php", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]location['"]/, "Login location"],
		["app/Actions/SysAdmin/WebUserRequest/UI/Traits/WithWebUserRequestsUI.php", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]location['"]/, "Login location"],
		["app/Actions/SysAdmin/UI/IndexUserRequests.php", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]location['"]/, "Login location"],
		["app/Actions/HumanResources/Employee/UI/IndexEmployeeUserActivity.php", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]location['"]/, "Login location"],
		["app/Actions/Retina/UI/SysAdmin/IndexRetinaWebUsers.php", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]location['"]/, "Login location"],
		["app/Actions/CRM/AppointmentType/UI/WithAppointmentTypeForm.php", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]Location['"]/, "Appointment location"],
		["resources/js/Pages/Grp/Org/Shop/CRM/Appointment.vue", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]Location['"]/, "Appointment location"],
		["resources/js/Components/Forms/Fields/AppointmentLocation.vue", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]Location['"]/, "Appointment location"],
		["resources/js/Pages/Grp/Org/Calendar/OrganisationCalendar.vue", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]Location['"]/, "Event location"],
		["resources/js/Components/HumanResources/WorkplaceShowcase.vue", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]Location['"]/, "Workplace location"],
		["resources/js/Pages/Grp/Org/Production/ToRestock.vue", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]Lead time['"]/, "Production lead time"],
		["resources/js/Components/Seo/SeoDashboardJourney.vue", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]Placed['"]/, "Order placed"],
		["resources/js/Components/Tables/Grp/Org/Procurement/TablePurchaseOrderTransactions.vue", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]Packing['"]/, "Packing instructions"],
		["app/Enums/UI/Procurement/OrgSupplierProductTabsEnum.php", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]Packing['"]/, "Packing details"],
		["app/Actions/Procurement/OrgSupplierProducts/UI/EditOrgSupplierProduct.php", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]Packing['"]/, "Packing details"],
		["app/Actions/SupplyChain/Supplier/UI/WithSupplierEditFields.php", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]Packing['"]/, "Packing details"],
		["app/Actions/GoodsIn/StockDelivery/UI/GetStockDeliveryGoodsInChecklist.php", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]Packing['"]/, "Packing details"],
		["app/Actions/SupplyChain/Supplier/WithSupplierPacking.php", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]Packing['"]/, "Packing method"],
		["app/Actions/Inventory/OrgStock/UI/EditOrgStockComposition.php", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]Packing['"]/, "Packing composition"],
		["app/Actions/Inventory/OrgStock/UI/ShowOrgStock.php", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]Packing['"]/, "Packing composition"],
		["app/Enums/Catalogue/Charge/ChargeTypeEnum.php", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]Packing['"]/, "Packing charge"],
		["app/Enums/Dispatching/DeliveryNoteLeaflet/DeliveryNoteLeafletStateEnum.php", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]Included['"]/, "Included in parcel"],
		["app/Enums/Comms/Mailshot/MailshotUtmParameterEnum.php", /(ctrans|ctransChoice|trans|__|trans_choice|\$t)\(\s*['"]Term['"]/, "Search term"],
		["resources/js/Components/Goods/ProductDevelopment/Design/DesignFaceEditor.vue", /ctrans\("Outline"\)/, "Outline colour"],
		["resources/js/Pages/Grp/Procurement/PartnerShoppingDashboard.vue", /ctrans\("Fill"\)/, "Auto-fill"],
		["resources/js/Components/Websites/WebsiteDialog/WebsiteDialogSettings.vue", /ctrans\("Display"\)/, "When it shows"],
		["app/Actions/Web/WebsiteVisitor/UI/IndexWebsiteVisitors.php", /__\('Bounced'\)/, "Bounced visit"],
		["app/Actions/UI/Marketing/ExportSocialMediaPosts.php", /__\('Follows'\)/, "New follows"],
		["resources/js/Composables/useFormatTime.ts", /ctrans\('passed'\)/, "Date passed"],
		["resources/js/Components/TableSetPriceProduct.vue", /ctrans\('Margin'\)/, "Profit margin"],
		["resources/js/Components/Margin/MarginSummary.vue", /ctrans\(["']Margin["']\)/, "Profit margin"],
		["resources/js/Components/Margin/MarginSummary.vue", /ctrans\(["']margin["']\)/, "profit margin"],
		["resources/js/Components/Pure/MasterPriceCurrencyTable.vue", /margin-label="Margin"/, "Profit margin"],
		["resources/js/Components/Pure/MasterRrpCurrencyTable.vue", /margin-label="Margin"/, "Profit margin"],
		["app/Actions/Accounting/InvoiceTransaction/UI/IndexInvoiceTransactions.php", /__\('Margin'\)/, "Profit margin"],
		["app/Actions/Ordering/Order/UI/ShowOrder.php", /__\('Margin'\)/, "Profit margin"],
		["app/Actions/Ordering/Transaction/UI/IndexTransactions.php", /__\('Margin'\)/, "Profit margin"],
		["resources/js/Components/Goods/ProductDevelopment/ProductDevelopmentDesign.vue", /ctrans\("Review"\)/, "Review artwork"],
		["app/Actions/SupplyChain/AgentPayment/UI/IndexAgentLedger.php", /__\('Review'\)/, "Review status"],
		["resources/js/Components/Tickets/TicketLinks.vue", /ctrans\('Link'\)/, "Link this ticket"],
		["resources/js/Components/Goods/TradeUnitComposition.vue", /ctrans\('Link'\)/, "Link this artefact"],
		["resources/js/Components/Goods/ProductDevelopment/ProductDevelopmentPurchaseOrders.vue", /ctrans\('Link'\)/, "Link this purchase order"],
		["resources/js/Components/Goods/ProductDevelopment/ProductDevelopmentSupplierProductLink.vue", /ctrans\('Link'\)/, "Link this supplier product"],
		["resources/js/Components/DevOps/CodeHealth.vue", /ctrans\("Lines"\)/, "Lines of code"],
		["resources/js/Pages/Grp/Org/Web/Workshop/Sidebar/Blueprint.ts", /label: "Lines",/, "Line colour"],
	]
	const english = JSON.parse(readFileSync(join(langDirectory, "en.json"), "utf8"))
	const slovak = JSON.parse(readFileSync(join(langDirectory, "sk.json"), "utf8"))

	for (const [file, sharedKey, ownKey] of separated) {
		const source = readFileSync(join(root, file), "utf8")
		assert.doesNotMatch(source, sharedKey, file)
		assert.ok(source.includes(`'${ownKey}'`) || source.includes(`"${ownKey}"`), `${file} must use ${ownKey}`)
		assert.ok(ownKey in english && ownKey in slovak, `${ownKey} must be in en.json and sk.json`)
	}

	assert.equal(slovak["Free"], "Zadarmo")
	assert.equal(slovak["Off"], "Zľava")
	assert.equal(slovak["Gold Reward"], "Gold Reward")
	assert.notEqual(slovak["Shopping"], slovak["Shopping results"])
})

test("manufacture floor strings keep their placeholders in the languages artisans use", () => {
	const floorFiles = [
		"resources/js/Pages/Grp/Org/Production/ManufactureFloor.vue",
		"resources/js/Pages/Grp/Org/Production/MyPerformance.vue",
		"resources/js/Components/ManufactureWorkingCard.vue",
	]
	const keys = floorFiles.flatMap((file) =>
		[...readFileSync(join(root, file), "utf8").matchAll(/ctrans(?:Choice)?\(\s*(['"])((?:(?!\1).)+)\1/g)].map((match) => match[2])
	)
	const placeholders = (text: string) => [...new Set(text.match(/:[a-zA-Z_]+\b/g) ?? [])].sort()

	assert.ok(keys.length > 100)

	for (const locale of ["ro", "pl", "sk", "lv"]) {
		const strings = JSON.parse(readFileSync(join(langDirectory, `${locale}.json`), "utf8"))
		for (const key of keys.filter((key) => strings[key])) {
			assert.deepEqual(placeholders(strings[key]), placeholders(key), `${locale}: ${key}`)
		}
	}

	assert.equal(JSON.parse(readFileSync(join(langDirectory, "ro.json"), "utf8"))["Show"], "Afișează")
})
