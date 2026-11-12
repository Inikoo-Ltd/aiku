import SwiftUI

struct ShellWarehouseSheet: View {
    @Environment(Session.self) private var session
    @Environment(ToastCenter.self) private var toasts
    let onClose: () -> Void
    @State private var selected: ShellWarehouseChoice?
    @State private var warehouseStore = WarehouseStore.shared

    var body: some View {
        let current = currentChoice
        let chosen = selected ?? current
        VStack(alignment: .leading, spacing: 12) {
            HStack(spacing: 10) {
                AppIcon.warehouse.image
                    .font(.system(size: 20))
                    .foregroundStyle(AikuColor.ink)
                    .accessibilityHidden(true)
                Text("Organisation & warehouse")
                    .font(.aiku(18, .bold))
                    .foregroundStyle(AikuColor.text)
                    .accessibilityAddTraits(.isHeader)
            }
            ForEach(organisations) { organisation in
                organisationGroup(organisation, chosen: chosen, current: current)
            }
            Text("Only organisations and warehouses you have permission for are listed. Every screen, list and scan follows your choice.")
                .font(.aiku(12))
                .foregroundStyle(AikuColor.text3)
                .fixedSize(horizontal: false, vertical: true)
            AikuButton(ShellWarehouseDirectory.confirmTitle(selected: chosen, current: current)) {
                apply(chosen, current: current)
            }
            .multilineTextAlignment(.center)
            .accessibilityIdentifier("shell.switchWarehouse")
        }
        .padding(.horizontal, 20)
        .padding(.top, 28)
        .padding(.bottom, 16)
    }

    private var organisations: [ShellOrganisation] {
        warehouseStore.isLive
            ? ShellWarehouseDirectory.organisations(from: warehouseStore.warehouses)
            : ShellWarehouseDirectory.organisations
    }

    private var currentChoice: ShellWarehouseChoice {
        ShellWarehouseChoice(
            organisation: session.profile.organisation,
            warehouseName: session.profile.warehouseName,
            warehouseCode: session.profile.warehouseCode,
            warehouseID: warehouseStore.isLive ? warehouseStore.selectedID : nil
        )
    }

    private func organisationGroup(_ organisation: ShellOrganisation, chosen: ShellWarehouseChoice, current: ShellWarehouseChoice) -> some View {
        VStack(alignment: .leading, spacing: 8) {
            HStack(spacing: 10) {
                Text(organisation.initials)
                    .font(.aiku(11, .bold))
                    .foregroundStyle(AikuColor.onNav)
                    .frame(width: 28, height: 28)
                    .background(AikuColor.nav, in: RoundedRectangle(cornerRadius: 6))
                    .accessibilityHidden(true)
                Text(organisation.name)
                    .font(.aiku(14, .bold))
                    .foregroundStyle(AikuColor.text)
                    .frame(maxWidth: .infinity, alignment: .leading)
                Text(organisation.warehouseCountText)
                    .font(.aiku(12))
                    .foregroundStyle(AikuColor.text3)
            }
            .padding(.top, 4)
            VStack(spacing: 0) {
                ForEach(Array(organisation.warehouses.enumerated()), id: \.element.id) { index, warehouse in
                    if index > 0 {
                        RowDivider()
                    }
                    let choice = ShellWarehouseDirectory.choice(organisation: organisation, warehouse: warehouse)
                    warehouseButton(warehouse, choice: choice, isOn: choice == chosen, isCurrent: choice == current)
                }
            }
            .clipShape(RoundedRectangle(cornerRadius: 10))
            .overlay(RoundedRectangle(cornerRadius: 10).strokeBorder(AikuColor.border))
        }
        .accessibilityElement(children: .contain)
        .accessibilityLabel(organisation.name)
    }

    private func warehouseButton(_ warehouse: ShellWarehouse, choice: ShellWarehouseChoice, isOn: Bool, isCurrent: Bool) -> some View {
        Button {
            selected = choice
        } label: {
            HStack(spacing: 12) {
                AppIcon.warehouse.image
                    .font(.system(size: 18))
                    .foregroundStyle(AikuColor.text2)
                    .accessibilityHidden(true)
                VStack(alignment: .leading, spacing: 2) {
                    Text(warehouse.name)
                        .font(.aiku(15, .semibold))
                        .foregroundStyle(AikuColor.text)
                    Text(isCurrent ? "\(warehouse.code) · current" : warehouse.code)
                        .font(.aiku(12))
                        .foregroundStyle(AikuColor.text3)
                }
                .multilineTextAlignment(.leading)
                .frame(maxWidth: .infinity, alignment: .leading)
                AppIcon.check.image
                    .font(.system(size: 16, weight: .bold))
                    .foregroundStyle(AikuColor.link)
                    .opacity(isOn ? 1 : 0)
                    .accessibilityHidden(true)
            }
            .padding(.horizontal, 14)
            .frame(minHeight: 56)
            .background(isOn ? AikuColor.soft : AikuColor.surface)
            .overlay(Rectangle().strokeBorder(isOn ? AikuColor.primary : .clear, lineWidth: 2))
            .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
        .accessibilityAddTraits(isOn ? .isSelected : [])
    }

    private func apply(_ choice: ShellWarehouseChoice, current: ShellWarehouseChoice) {
        if choice != current {
            if let warehouseID = choice.warehouseID {
                warehouseStore.select(warehouseID)
            }
            session.profile.organisation = choice.organisation
            session.profile.warehouseName = choice.warehouseName
            session.profile.warehouseCode = choice.warehouseCode
            toasts.show("Switched to \(choice.organisation) · \(choice.warehouseName)")
        }
        onClose()
    }
}

extension View {
    func shellWarehouseSheet(isPresented: Binding<Bool>) -> some View {
        aikuSheet(isPresented: isPresented) {
            ShellWarehouseSheet { isPresented.wrappedValue = false }
        }
    }
}
