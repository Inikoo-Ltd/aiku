import SwiftUI

struct ShellMenuRow: View {
    let title: String
    var subtitle: String?
    var subtitleTone: Tone?
    let icon: AppIcon
    var iconTone: Tone = .info
    var badge: ShellMenuBadge?
    let action: () -> Void

    var body: some View {
        Button(action: action) {
            HStack(spacing: 12) {
                IconTile(icon, tone: iconTone, size: 36, radius: 8)
                VStack(alignment: .leading, spacing: 2) {
                    Text(title)
                        .font(.aiku(15, .semibold))
                        .foregroundStyle(AikuColor.text)
                    if let subtitle {
                        Text(subtitle)
                            .font(.aiku(12))
                            .foregroundStyle(subtitleTone?.foreground ?? AikuColor.text3)
                    }
                }
                .multilineTextAlignment(.leading)
                .frame(maxWidth: .infinity, alignment: .leading)
                if let badge {
                    ShellMenuBadgeView(badge: badge)
                }
                AppIcon.chevronRight.image
                    .font(.system(size: 14, weight: .semibold))
                    .foregroundStyle(AikuColor.faint)
                    .accessibilityHidden(true)
            }
            .padding(.horizontal, 16)
            .padding(.vertical, 6)
            .frame(minHeight: 56)
            .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
    }
}

struct ShellMenuBadgeView: View {
    let badge: ShellMenuBadge

    var body: some View {
        switch badge {
        case .pill(let chip):
            StatusChip(chip, style: .pill)
        case .fulfilmentTag:
            ShellOutlineTag(text: "Fulfilment")
        }
    }
}

struct ShellOutlineTag: View {
    let text: String

    var body: some View {
        Text(text)
            .font(.aiku(11, .semibold))
            .foregroundStyle(AikuColor.text3)
            .lineLimit(1)
            .padding(.horizontal, 6)
            .padding(.vertical, 2)
            .overlay(RoundedRectangle(cornerRadius: 6).strokeBorder(AikuColor.borderStrong))
    }
}

struct ShellValueRow: View {
    let title: String
    var value: String?
    var valueTone: Tone?
    var action: (() -> Void)?

    var body: some View {
        if let action {
            Button(action: action) { label }
                .buttonStyle(.plain)
        } else {
            label
        }
    }

    private var label: some View {
        HStack(spacing: 12) {
            Text(title)
                .font(.aiku(15))
                .foregroundStyle(AikuColor.text)
                .frame(maxWidth: .infinity, alignment: .leading)
            if let value {
                Text(value)
                    .font(.aiku(14))
                    .foregroundStyle(valueTone?.foreground ?? AikuColor.text3)
                    .lineLimit(1)
                    .minimumScaleFactor(0.8)
            }
            if action != nil {
                AppIcon.chevronRight.image
                    .font(.system(size: 13, weight: .semibold))
                    .foregroundStyle(AikuColor.faint)
                    .accessibilityHidden(true)
            }
        }
        .padding(.horizontal, 16)
        .frame(minHeight: 52)
        .contentShape(Rectangle())
    }
}

struct ShellRadioDot: View {
    let isOn: Bool

    var body: some View {
        Circle()
            .strokeBorder(isOn ? AikuColor.primary : AikuColor.borderStrong, lineWidth: isOn ? 7 : 2)
            .background(Circle().fill(AikuColor.surface))
            .frame(width: 22, height: 22)
            .accessibilityHidden(true)
    }
}

struct ShellGroupHeading: View {
    let title: String
    let icon: AppIcon
    var note: String?

    var body: some View {
        HStack(spacing: 8) {
            icon.image
                .font(.system(size: 14))
                .accessibilityHidden(true)
            Text(title.uppercased())
                .font(.aiku(12, .bold))
                .tracking(0.7)
                .accessibilityAddTraits(.isHeader)
            Spacer(minLength: 8)
            if let note {
                Text(note)
                    .font(.aiku(11, .semibold))
                    .foregroundStyle(AikuColor.text4)
            }
        }
        .foregroundStyle(AikuColor.text3)
        .padding(.horizontal, 4)
    }
}

struct ShellWarehouseRow: View {
    let label: String
    let value: String
    var showsIcon = true
    let action: () -> Void

    var body: some View {
        Button(action: action) {
            HStack(spacing: 12) {
                if showsIcon {
                    AppIcon.warehouse.image
                        .font(.system(size: 19))
                        .foregroundStyle(AikuColor.ink)
                        .accessibilityHidden(true)
                }
                VStack(alignment: .leading, spacing: 2) {
                    Text(label)
                        .font(.aiku(12))
                        .foregroundStyle(AikuColor.text3)
                    Text(value)
                        .font(.aiku(15, .semibold))
                        .foregroundStyle(AikuColor.text)
                        .lineLimit(2)
                        .minimumScaleFactor(0.85)
                }
                .multilineTextAlignment(.leading)
                .frame(maxWidth: .infinity, alignment: .leading)
                Text("Change")
                    .font(.aiku(14, .semibold))
                    .foregroundStyle(AikuColor.link)
            }
            .padding(.horizontal, 14)
            .padding(.vertical, 8)
            .frame(minHeight: 56)
            .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
        .accessibilityHint("Change organisation and warehouse")
        .accessibilityIdentifier("shell.changeWarehouse")
    }
}
