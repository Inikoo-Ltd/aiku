import { AuthContext } from "@/components/context/AuthContext";
import request from "@/utils/Request";
import { retrieveProfile } from "@/utils/user";
import { Camera, CameraView } from "expo-camera";
import { useRouter } from "expo-router";
import React, { useContext, useEffect, useState } from "react";
import { Button, StyleSheet, Text, View } from "react-native";
import { ALERT_TYPE, Toast } from "react-native-alert-notification";

export default function App() {
  const [hasPermission, setHasPermission] = useState(null);
  const [scanned, setScanned] = useState(false);
  const { signIn } = useContext(AuthContext);
  const router = useRouter();

  useEffect(() => {
    const getCameraPermissions = async () => {
      const { status } = await Camera.requestCameraPermissionsAsync();
      setHasPermission(status === "granted");
    };
    getCameraPermissions();
  }, []);

  const handleLogin = async (code) => {
    return new Promise((resolve) => {
      request({
        method: "post",
        urlKey: "login-scanner",
        autoRefreshExpiredToken: false,
        headers: {
          "Content-Type": "multipart/form-data",
        },
        data: {
          code,
          device_name: "android",
        },
        onBoth: async (isSuccess, userRes) => {
          if (isSuccess) {
            await retrieveProfile({
              accessToken: userRes.token,
              onSuccess: async (profileRes) => {
                const user = { ...userRes, ...profileRes.data };
                signIn(user);
                router.replace("/(drawer-setup)/home");
                resolve(true);
              },
              onFailed: (err) => {
                Toast.show({
                  type: ALERT_TYPE.DANGER,
                  title: "Error",
                  textBody: err?.data?.message || "Failed to retrieve profile",
                });
                console.error("Profile Retrieval Failed:", err);
                resolve(false);
              },
            });
          } else {
            Toast.show({
              type: ALERT_TYPE.DANGER,
              title: "Error",
              textBody: userRes?.data?.message || "Login failed",
            });
            resolve(false);
          }
        },
      });
    });
  };

  const handleBarcodeScanned = async ({ type, data }) => {
    setScanned(true);

    const success = await handleLogin(data);
    if (!success) {
      // Allow re-scanning on failure
      setScanned(false);
    }
  };

  if (hasPermission === null) {
    return <Text>Requesting for camera permission</Text>;
  }

  if (hasPermission === false) {
    return <Text>No access to camera</Text>;
  }

  return (
    <View style={styles.container}>
      <CameraView
        onBarcodeScanned={scanned ? undefined : handleBarcodeScanned}
        barcodeScannerSettings={{ barcodeTypes: ["qr", "pdf417"] }}
        style={StyleSheet.absoluteFillObject}
      />
      {scanned && (
        <Button title="Tap to Scan Again" onPress={() => setScanned(false)} />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    flexDirection: "column",
    justifyContent: "center",
  },
});
