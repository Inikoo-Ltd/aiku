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

    private func requiredError(_ text: String, _ label: String) -> String? {
        guard showsErrors, text.trimmingCharacters(in: .whitespaces).isEmpty else { return nil }
        return "Enter the \(label)"
    }

    var body: some View {
        SheetScaffold(title: "Edit delivery address", subtitle: "Delivery note \(note.reference) · used for the shipping label") {
            AikuTextField("Contact name", text: $contactName, error: requiredError(contactName, "contact name"))
            AikuTextField("Company", text: $company)
            AikuTextField("Phone", text: $phone, keyboard: .phonePad)
            AikuTextField("Email", text: $email, keyboard: .emailAddress)
            AikuTextField("Address", text: $street, error: requiredError(street, "street and number"))
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
                GoodsOutFeedback.done("Delivery address of \(note.reference) saved", previous: previous, toasts: toasts)
            }
            .accessibilityIdentifier("goodsOut.saveAddress")
            AikuButton("Cancel", kind: .tertiary, action: close)
        }
    }
}
