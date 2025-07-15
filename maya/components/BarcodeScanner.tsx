import { Camera, CameraView } from "expo-camera";
import { useEffect, useState } from "react";
import { ActivityIndicator, Button, StyleSheet, Text, View } from "react-native";

export default function BarcodeScanner({ onScan }: { onScan: (data: string) => Promise<boolean> }) {
  const [hasPermission, setHasPermission] = useState<boolean | null>(null);
  const [scanned, setScanned] = useState(false);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    const requestPermission = async () => {
      const { status } = await Camera.requestCameraPermissionsAsync();
      setHasPermission(status === "granted");
    };
    requestPermission();
  }, []);

  const handleBarcodeScanned = async ({ data }: { data: string }) => {
    setScanned(true);
    setLoading(true);
    const success = await onScan(data);
    setLoading(false);
    if (!success) setScanned(false);
  };

  if (hasPermission === null) {
    return <Text style={styles.centerText}>Requesting camera permission...</Text>;
  }

  if (hasPermission === false) {
    return <Text style={styles.centerText}>No access to camera</Text>;
  }

  return (
    <View style={styles.container}>
      <CameraView
        onBarcodeScanned={scanned ? undefined : handleBarcodeScanned}
        style={StyleSheet.absoluteFillObject}
      />

      {/* Overlay for scanner guidance */}
      {!scanned && !loading && (
        <View style={styles.overlayContainer}>
          <View style={styles.scannerBox}>
            <Text style={styles.overlayText}></Text>
          </View>
        </View>
      )}

      {loading && (
        <View style={styles.loadingOverlay}>
          <ActivityIndicator size="large" color="#fff" />
        </View>
      )}

      {scanned && !loading && (
        <View style={styles.buttonContainer}>
          <Button title="Tap to Scan Again" onPress={() => setScanned(false)} />
        </View>
      )}
    </View>
  );
}


const styles = StyleSheet.create({
  container: {
    flex: 1,
  },
  centerText: {
    flex: 1,
    textAlign: "center",
    textAlignVertical: "center",
    fontSize: 16,
  },
  overlayContainer: {
    ...StyleSheet.absoluteFillObject,
    justifyContent: "center",
    alignItems: "center",
    zIndex: 10,
  },
  scannerBox: {
    borderColor: "#6366F1",
    borderWidth: 3,
    borderRadius: 16,
    width: 250,
    height: 250,
    justifyContent: "center",
    alignItems: "center",
    backgroundColor: "rgba(0,0,0,0.2)",
  },
  overlayText: {
    marginTop: 12,
    color: "#fff",
    fontSize: 16,
    fontWeight: "500",
  },
  loadingOverlay: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: "rgba(0,0,0,0.5)",
    justifyContent: "center",
    alignItems: "center",
    zIndex: 20,
  },
  buttonContainer: {
    position: "absolute",
    bottom: 40,
    alignSelf: "center",
    backgroundColor: "white",
    borderRadius: 8,
    overflow: "hidden",
    zIndex: 20,
  },
});
