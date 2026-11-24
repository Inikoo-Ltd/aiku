import SwiftUI

struct GoodsOutAddressSheet: View {
    @Environment(ToastCenter.self) private var toasts
    let note: GoodsOutDeliveryNote
    let close: () -> Void
    @State private var contactName: String
    @State private var company: String
    @State private var phone: String
    @State private var email: String
    @State private var street: String
    @State private var town: String
    @State private var postcode: String
    @State private var country: String
    @State private var showsErrors = false
    private let store = GoodsOutStore.shared

    init(note: GoodsOutDeliveryNote, close: @escaping () -> Void) {
        self.note = note
        self.close = close
        _contactName = State(initialValue: note.contactName)
        _company = State(initialValue: note.deliveryAddress.company)
        _phone = State(initialValue: note.phone)
        _email = State(initialValue: note.email)
        _street = State(initialValue: note.deliveryAddress.street)
        _town = State(initialValue: note.deliveryAddress.town)
        _postcode = State(initialValue: note.deliveryAddress.postcode)
        _country = State(initialValue: note.deliveryAddress.country)
    }

    private func requiredError(_ text: String, _ message: String) -> String? {
        guard showsErrors, text.trimmingCharacters(in: .whitespaces).isEmpty else { return nil }
        return message
    }

    var body: some View {
        if note.isRemote {
            GoodsOutRemoteAddressSheet(note: note, close: close)
        } else {
            sampleBody
        }
    }

    private var sampleBody: some View {
        SheetScaffold(title: "Edit delivery address", subtitle: L10n.format("Delivery note %@ · used for the shipping label", note.reference)) {
            AikuTextField("Contact name", text: $contactName, error: requiredError(contactName, "Enter the contact name"))
            AikuTextField("Company", text: $company)
            AikuTextField("Phone", text: $phone, keyboard: .phonePad)
            AikuTextField("Email", text: $email, keyboard: .emailAddress)
            AikuTextField("Address", text: $street, error: requiredError(street, "Enter the street and number"))
            HStack(alignment: .top, spacing: 8) {
                AikuTextField("Town", text: $town)
                AikuTextField("Postcode", text: $postcode)
            }
            AikuTextField("Country", text: $country)
            HelperText("Changes apply to this delivery note’s shipping only. A banned delivery address (listed in Shop settings) is flagged with a warning.")
        } footer: {
            AikuButton("Save address") {
                let address = GoodsOutAddress(company: company, street: street, town: town, postcode: postcode, country: country)
                let previous = store.updateAddress(note.reference, address: address, contactName: contactName, phone: phone, email: email)
                guard previous != nil else {
                    showsErrors = true
                    return
                }
                close()
                GoodsOutFeedback.done(L10n.format("Delivery address of %@ saved", note.reference), previous: previous, toasts: toasts)
            }
            .accessibilityIdentifier("goodsOut.saveAddress")
            AikuButton("Cancel", kind: .tertiary, action: close)
        }
    }
}

struct GoodsOutAddressRetry: Hashable, Identifiable {
    let message: String
    let shipperID: Int
    let overrideLock: Bool

    var id: String { "\(shipperID)-\(overrideLock)" }
}

struct GoodsOutRemoteAddressSheet: View {
    @Environment(ToastCenter.self) private var toasts
    let note: GoodsOutDeliveryNote
    var retry: GoodsOutAddressRetry?
    var onRetried: (GoodsOutWriteResult, GoodsOutShipmentRequest) -> Void = { _, _ in }
    let close: () -> Void
    @State private var form: GoodsOutAddressForm
    @State private var fieldErrors: [String: String] = [:]
    @State private var problem: String?
    private let store = GoodsOutStore.shared

    init(note: GoodsOutDeliveryNote, retry: GoodsOutAddressRetry? = nil, onRetried: @escaping (GoodsOutWriteResult, GoodsOutShipmentRequest) -> Void = { _, _ in }, close: @escaping () -> Void) {
        self.note = note
        self.retry = retry
        self.onRetried = onRetried
        self.close = close
        _form = State(initialValue: note.packingDetails?.address ?? GoodsOutAddressForm())
        _problem = State(initialValue: retry?.message)
    }

    private var includesContact: Bool { retry != nil || note.packingDetails?.canEditContact == true }

    var body: some View {
        SheetScaffold(title: "Edit delivery address", subtitle: L10n.format("Delivery note %@ · used for the shipping label", note.reference)) {
            if let problem {
                InlineAlert(tone: .danger, title: problem)
            }
            if includesContact {
                AikuTextField("Contact name", text: $form.contactName, error: fieldErrors["contact_name"])
                AikuTextField("Company", text: $form.companyName, error: fieldErrors["company_name"])
                AikuTextField("Phone", text: $form.phone, error: fieldErrors["phone"], keyboard: .phonePad)
                AikuTextField("Email", text: $form.email, error: fieldErrors["email"], keyboard: .emailAddress)
            }
            AikuTextField("Address", text: $form.addressLine1, error: fieldErrors["address.address_line_1"])
            AikuTextField("Address line 2", text: $form.addressLine2, error: fieldErrors["address.address_line_2"])
            HStack(alignment: .top, spacing: 8) {
                AikuTextField("Town", text: $form.locality, error: fieldErrors["address.locality"])
                AikuTextField("Postcode", text: $form.postalCode, error: fieldErrors["address.postal_code"])
            }
            AikuTextField("County or region", text: $form.administrativeArea, error: fieldErrors["address.administrative_area"])
            AikuTextField("Country code", text: $form.countryCode, placeholder: "GB", error: fieldErrors["address.country_code"])
            HelperText(retry != nil
                ? "The carrier refused this address. Fix it, then the shipment is tried again with the same shipper. Changes apply to this delivery note’s shipping only."
                : "Changes apply to this delivery note’s shipping only, not to the order. The address can’t be changed once a shipment exists.")
        } footer: {
            AikuButton(retry != nil ? "Save and try again" : "Save address", isLoading: store.isWorking(on: note.reference)) {
                save()
            }
            .accessibilityIdentifier("goodsOut.saveAddress")
            AikuButton("Cancel", kind: .tertiary, action: close)
        }
    }

    private func save() {
        guard form.countryCode.trimmingCharacters(in: .whitespaces).count == 2 else {
            fieldErrors = ["address.country_code": "Enter the two-letter country code, for example GB."]
            return
        }
        fieldErrors = [:]
        let request = retry.map { GoodsOutShipmentRequest.retry(shipperID: $0.shipperID, address: form, overrideLock: $0.overrideLock) }
        let action = request?.action ?? .editAddress(form, includesContact: includesContact)
        Task {
            let result = await store.write(action, on: note.reference)
            if let request, result.failureMessage == nil || result.fieldErrors["override_lock"] != nil {
                onRetried(result, request)
                return
            }
            if request != nil {
                fieldErrors = result.fieldErrors
                problem = result.fieldErrors["address"] ?? result.fieldErrors["message"] ?? result.failureMessage
                return
            }
            if GoodsOutWriteFeedback.show(result, fallback: "Delivery address saved", toasts: toasts, showsFailure: false) {
                close()
            } else {
                fieldErrors = result.fieldErrors
                problem = result.fieldErrors["address"] ?? result.failureMessage
            }
        }
    }
}
