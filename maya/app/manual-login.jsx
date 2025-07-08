import { AuthContext } from '@/components/context/AuthContext';
import FormTextInput from "@/components/FormTextInput";
import request from "@/utils/Request";
import { retrieveProfile } from '@/utils/user';
import { useRouter } from "expo-router";
import React, { useContext, useState } from 'react';
import {
  ImageBackground,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from "react-native";
import { ALERT_TYPE, Toast } from "react-native-alert-notification";

const ManualLoginScreen = () => {
  const router = useRouter();
  const { signIn } = useContext(AuthContext);
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);

  const handleLogin = async () => {
    return new Promise((resolve) => {
      request({
        method: "post",
        urlKey: "login",
        autoRefreshExpiredToken: false,
        headers: {
          "Content-Type": "multipart/form-data",
        },
        data: {
          username: email,
          password: password,
          device_name: "android",
        },
        onBoth: async (isSuccess, userRes) => {
          if (isSuccess) {
            await retrieveProfile({
              accessToken: userRes.token,
              onSuccess: async (profileRes) => {
                console.log('dsf',profileRes)
                const user = { ...userRes, ...profileRes.data };
                signIn(user); // enable if you're using AuthContext
                router.replace("/home"); // or your intended route
                resolve(true);
              },
              onFailed: (err) => {
                Toast.show({
                  type: ALERT_TYPE.DANGER,
                  title: "Error",
                  textBody: err?.data.message || "failed from server",
                });
                console.error("Profile Retrieval Failed:", err);
              },
            });
          } else {
            Toast.show({
              type: ALERT_TYPE.DANGER,
              title: "Error",
              textBody: userRes?.data.message || "failed from server",
            });
            resolve(false);
          }
        },
      });
    });
  };

  return (
    <ImageBackground
      source={require("@/assets/images/background-guest.webp")}
      style={styles.background}
    >
      <View style={styles.card}>
        <FormTextInput
          label="Username"
          placeholder="Johndoe"
          value={email}
          onChangeText={setEmail}
          keyboardType="email-address"
        />

        <FormTextInput
          label="Password"
          placeholder="Password"
          value={password}
          onChangeText={setPassword}
          secure
          showToggle
          isPasswordVisible={showPassword}
          onToggleVisibility={() => setShowPassword((prev) => !prev)}
        />

        <View style={styles.buttonGroup}>
          <TouchableOpacity style={styles.primaryButton} onPress={handleLogin}>
            <Text style={styles.primaryButtonText}>Login</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={styles.secondaryButton}
            onPress={() => router.push("/scanner-login")}
          >
            <Text style={styles.secondaryButtonText}>Use Scanner</Text>
          </TouchableOpacity>
        </View>
      </View>

      <View style={styles.logoContainer}>
        <Text style={styles.logoText}>Aiku</Text>
      </View>
    </ImageBackground>
  );
};

const styles = StyleSheet.create({
  background: {
    flex: 1,
    justifyContent: "center",
    alignItems: "center",
  },
  card: {
    width: 320,
    padding: 24,
    backgroundColor: "#fff",
    borderRadius: 16,
    borderColor: "#ccc",
    borderWidth: 1,
    shadowColor: "#000",
    shadowOpacity: 0.1,
    shadowRadius: 8,
    elevation: 5,
  },
  buttonGroup: {
    marginTop: 16,
    gap: 12,
  },
  primaryButton: {
    backgroundColor: "#1D4ED8",
    paddingVertical: 12,
    borderRadius: 8,
    alignItems: "center",
  },
  primaryButtonText: {
    color: "#fff",
    fontSize: 16,
    fontWeight: "600",
  },
  secondaryButton: {
    backgroundColor: "#E5E7EB",
    paddingVertical: 12,
    borderRadius: 8,
    alignItems: "center",
  },
  secondaryButtonText: {
    fontSize: 16,
    fontWeight: "600",
    color: "#374151",
  },
  logoContainer: {
    position: "absolute",
    bottom: 20,
    left: 20,
    flexDirection: "row",
    alignItems: "center",
  },
  logoText: {
    fontSize: 40,
    fontWeight: "bold",
    color: "white",
    marginTop: 10,
  },
});

export default ManualLoginScreen;
