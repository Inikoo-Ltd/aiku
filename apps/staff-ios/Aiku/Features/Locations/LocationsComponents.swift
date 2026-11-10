import SwiftUI

struct LocationsLocationRow: View {
    let location: LocationsLocation
    var subtitle: String?
    let action: () -> Void

    var body: some View {
        ListRow(
            location.code,
            subtitle: subtitle ?? location.listSubtitle,
            icon: .locations,
            iconTone: location.status == .broken ? .danger : .info,
            chips: [location.chip],
            action: action
        )
        .accessibilityIdentifier("locations.row.\(location.code)")
    }
}

struct LocationsLocationList: View {
    let locations: [LocationsLocation]
    let onOpen: (LocationsLocation) -> Void

    var body: some View {
        CardList(locations) { location in
            LocationsLocationRow(location: location) { onOpen(location) }
        }
    }
}

struct LocationsSlotThumb: View {
    let reference: String
    var size: CGFloat = 48

    var body: some View {
        ProductThumb(size: size, label: "Product photo of \(reference)")
    }
}

struct LocationsListTabPill: View {
    let title: String
    let isSelected: Bool
    let action: () -> Void

    var body: some View {
        Button(action: action) {
            Text(title)
                .font(.aiku(13, .semibold))
                .lineLimit(1)
                .foregroundStyle(isSelected ? AikuColor.onPrimary : AikuColor.text2)
                .padding(.horizontal, 12)
                .frame(minHeight: 34)
                .background(isSelected ? AikuColor.primary : AikuColor.surface, in: Capsule())
                .overlay(Capsule().strokeBorder(isSelected ? AikuColor.primary : AikuColor.borderStrong))
                .frame(minHeight: 44)
                .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
        .accessibilityAddTraits(isSelected ? .isSelected : [])
    }
}

struct LocationsActionButton: View {
    let title: String
    var icon: AppIcon?
    var kind: AikuButtonKind = .tertiary
    let action: () -> Void

    var body: some View {
        AikuButton(title, icon: icon, kind: kind, action: action)
    }
}

struct LocationsOverviewLine: View {
    let icon: AppIcon
    let text: String

    var body: some View {
        HStack(spacing: 10) {
            icon.image
                .font(.system(size: 15))
                .foregroundStyle(AikuColor.text3)
                .frame(width: 18)
                .accessibilityHidden(true)
            Text(text)
                .font(.aiku(14))
                .foregroundStyle(AikuColor.text2)
                .frame(maxWidth: .infinity, alignment: .leading)
        }
        .frame(minHeight: 28)
    }
}

struct LocationsScanCover: View {
    let title: String
    var hint = "Point at the location label"
    let onClose: () -> Void
    let onScan: () -> Void
    @Environment(Session.self) private var session

    var body: some View {
        CameraScanScreen(
            kicker: "Locations · \(session.warehouseLabel)",
            title: title,
            hint: hint,
            onClose: onClose,
            onSimulateScan: onScan
        ) {
            EmptyView()
        } panel: {
            Text("Scan a location label (Code 128) to open it.")
                .font(.aiku(13))
                .foregroundStyle(AikuColor.text3)
                .multilineTextAlignment(.center)
                .frame(maxWidth: .infinity)
                .padding(.bottom, 8)
        }
    }
}
