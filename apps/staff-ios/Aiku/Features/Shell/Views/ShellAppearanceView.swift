import SwiftUI

struct ShellAppearanceView: View {
    @Bindable private var themeStore = ThemeStore.shared

    var body: some View {
        AppScreen(title: "Appearance", kicker: "My profile · Personal settings", spacing: 16) {
            Card(padding: 16, spacing: 12) {
                VStack(alignment: .leading, spacing: 2) {
                    Text("Mode")
                        .font(.aiku(15, .bold))
                        .foregroundStyle(AikuColor.text)
                        .accessibilityAddTraits(.isHeader)
                    Text("Dark is easier on the eyes in dim aisles and on night shifts.")
                        .font(.aiku(13))
                        .foregroundStyle(AikuColor.text3)
                }
                SegmentedControl(options: AppearanceMode.allCases, selection: $themeStore.mode, title: \.title) { mode in
                    switch mode {
                    case .light: .sun
                    case .dark: .moon
                    case .system: .phoneDevice
                    }
                }
                .accessibilityIdentifier("shell.appearance.mode")
            }
            Card(padding: 16, spacing: 12) {
                HStack(alignment: .firstTextBaseline) {
                    Text("Theme colour")
                        .font(.aiku(15, .bold))
                        .foregroundStyle(AikuColor.text)
                        .accessibilityAddTraits(.isHeader)
                    Spacer(minLength: 8)
                    Text(themeStore.theme.name)
                        .font(.aiku(13))
                        .foregroundStyle(AikuColor.text3)
                        .lineLimit(1)
                }
                LazyVGrid(columns: Array(repeating: GridItem(.flexible(), spacing: 10, alignment: .top), count: 3), spacing: 12) {
                    ForEach(ThemeID.allCases) { theme in
                        swatch(theme)
                    }
                }
            }
            preview
            Card {
                ToggleRow("Colour per organisation", subtitle: "Different bar colour for each organisation", isOn: $themeStore.colourPerOrganisation)
                    .padding(.vertical, 4)
            }
            Text("Saved to your profile — matches Appearance in the Aiku web app.")
                .font(.aiku(12))
                .foregroundStyle(AikuColor.text4)
                .multilineTextAlignment(.center)
                .padding(.horizontal, 12)
                .frame(maxWidth: .infinity)
        }
    }

    private func swatch(_ theme: ThemeID) -> some View {
        let isOn = themeStore.theme == theme
        let palette = Palette(theme: theme, dark: false)
        return Button {
            themeStore.theme = theme
        } label: {
            VStack(alignment: .leading, spacing: 6) {
                HStack(spacing: 0) {
                    VStack(spacing: 4) {
                        Capsule().fill(palette.navAccent.color).frame(height: 2)
                        Capsule().fill(palette.onNav2.color.opacity(0.7)).frame(height: 2)
                        Capsule().fill(palette.onNav2.color.opacity(0.7)).frame(height: 2)
                        Spacer(minLength: 0)
                    }
                    .padding(.vertical, 8)
                    .padding(.horizontal, 4)
                    .frame(width: 18)
                    .frame(maxHeight: .infinity)
                    .background(palette.nav.color)
                    VStack(alignment: .trailing, spacing: 6) {
                        RoundedRectangle(cornerRadius: 2).fill(palette.primary.color.opacity(0.55)).frame(width: 22, height: 4)
                        RoundedRectangle(cornerRadius: 2).fill(Color(hex: 0xE5E7EB)).frame(height: 3)
                        RoundedRectangle(cornerRadius: 2).fill(Color(hex: 0xE5E7EB)).frame(height: 3)
                            .padding(.leading, 14)
                        Spacer(minLength: 0)
                    }
                    .padding(8)
                    .frame(maxWidth: .infinity)
                }
                .frame(height: 62)
                .background(Color.white)
                .clipShape(RoundedRectangle(cornerRadius: 8))
                .overlay(
                    RoundedRectangle(cornerRadius: 8)
                        .strokeBorder(isOn ? AikuColor.primary : AikuColor.borderStrong, lineWidth: isOn ? 2 : 1)
                )
                .overlay(alignment: .topTrailing) {
                    if isOn {
                        AppIcon.check.image
                            .font(.system(size: 11, weight: .bold))
                            .foregroundStyle(AikuColor.onPrimary)
                            .frame(width: 22, height: 22)
                            .background(AikuColor.primary, in: Circle())
                            .overlay(Circle().strokeBorder(AikuColor.surface, lineWidth: 2))
                            .offset(x: 6, y: -6)
                    }
                }
                Text(theme.name)
                    .font(.aiku(12, .semibold))
                    .foregroundStyle(AikuColor.text2)
                    .multilineTextAlignment(.leading)
                    .fixedSize(horizontal: false, vertical: true)
            }
            .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
        .accessibilityLabel(theme.name)
        .accessibilityAddTraits(isOn ? .isSelected : [])
        .accessibilityIdentifier("shell.appearance.theme.\(theme.rawValue)")
    }

    private var preview: some View {
        VStack(alignment: .leading, spacing: 10) {
            Text("PREVIEW")
                .font(.aiku(12, .semibold))
                .tracking(0.7)
                .foregroundStyle(AikuColor.text3)
                .padding(.horizontal, 4)
            Card {
                HStack(spacing: 10) {
                    Text("Goods in")
                        .font(.aiku(15, .bold))
                        .foregroundStyle(AikuColor.onNav)
                        .frame(maxWidth: .infinity, alignment: .leading)
                    Text("Receiving")
                        .font(.aiku(12, .semibold))
                        .foregroundStyle(AikuColor.navAccent)
                        .padding(.horizontal, 10)
                        .frame(height: 30)
                        .background(AikuColor.navAct, in: RoundedRectangle(cornerRadius: 6))
                        .overlay(RoundedRectangle(cornerRadius: 6).strokeBorder(AikuColor.navRing))
                }
                .padding(.horizontal, 16)
                .padding(.vertical, 12)
                .background(AikuColor.nav)
                VStack(alignment: .leading, spacing: 12) {
                    HStack(spacing: 12) {
                        VStack(alignment: .leading, spacing: 2) {
                            Text("SDaw-0093")
                                .font(.aiku(15, .bold))
                                .foregroundStyle(AikuColor.text)
                            Text("Soy wax candle – vanilla")
                                .font(.aiku(13))
                                .foregroundStyle(AikuColor.text3)
                        }
                        .frame(maxWidth: .infinity, alignment: .leading)
                        StatusChip("In progress", tone: .warn)
                    }
                    ProgressBar(value: 0.51, height: 6)
                    HStack(spacing: 10) {
                        Text("Enter quantity")
                            .font(.aiku(14, .semibold))
                            .foregroundStyle(AikuColor.ink)
                            .frame(maxWidth: .infinity, minHeight: 44)
                            .background(AikuColor.soft, in: RoundedRectangle(cornerRadius: 6))
                            .overlay(RoundedRectangle(cornerRadius: 6).strokeBorder(AikuColor.softBd))
                        Text("Receive")
                            .font(.aiku(14, .semibold))
                            .foregroundStyle(AikuColor.onPrimary)
                            .frame(maxWidth: .infinity, minHeight: 44)
                            .background(AikuColor.primary, in: RoundedRectangle(cornerRadius: 6))
                    }
                }
                .padding(.horizontal, 16)
                .padding(.vertical, 14)
            }
            .accessibilityElement(children: .ignore)
            .accessibilityLabel("Preview of the selected theme")
        }
    }
}
