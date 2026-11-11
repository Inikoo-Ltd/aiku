import CoreLocation
import Foundation

enum LocationError: Error, Equatable {
    case denied
    case unavailable
}

@MainActor
final class LocationProvider: NSObject, CLLocationManagerDelegate {
    static let shared = LocationProvider()

    private let manager = CLLocationManager()
    private var authorizationContinuation: CheckedContinuation<CLAuthorizationStatus, Never>?
    private var locationContinuation: CheckedContinuation<CLLocation, Error>?

    override init() {
        super.init()
        manager.delegate = self
        manager.desiredAccuracy = kCLLocationAccuracyNearestTenMeters
    }

    func currentCoordinate() async throws -> ClockingCoordinate {
        let status = await authorization()
        guard status == .authorizedWhenInUse || status == .authorizedAlways else {
            throw LocationError.denied
        }
        let location = try await withCheckedThrowingContinuation { continuation in
            locationContinuation?.resume(throwing: LocationError.unavailable)
            locationContinuation = continuation
            manager.requestLocation()
        }
        return ClockingCoordinate(latitude: location.coordinate.latitude, longitude: location.coordinate.longitude)
    }

    private func authorization() async -> CLAuthorizationStatus {
        let status = manager.authorizationStatus
        guard status == .notDetermined else { return status }
        return await withCheckedContinuation { continuation in
            authorizationContinuation = continuation
            manager.requestWhenInUseAuthorization()
        }
    }

    nonisolated func locationManagerDidChangeAuthorization(_ manager: CLLocationManager) {
        let status = manager.authorizationStatus
        Task { @MainActor in
            guard status != .notDetermined else { return }
            authorizationContinuation?.resume(returning: status)
            authorizationContinuation = nil
        }
    }

    nonisolated func locationManager(_ manager: CLLocationManager, didUpdateLocations locations: [CLLocation]) {
        guard let location = locations.last else { return }
        Task { @MainActor in
            locationContinuation?.resume(returning: location)
            locationContinuation = nil
        }
    }

    nonisolated func locationManager(_ manager: CLLocationManager, didFailWithError error: Error) {
        Task { @MainActor in
            locationContinuation?.resume(throwing: LocationError.unavailable)
            locationContinuation = nil
        }
    }
}
