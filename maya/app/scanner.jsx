// screens/ScannerScreen.tsx
import BarcodeScanner from "@/components/BarcodeScanner";
import { AuthContext } from "@/components/context/AuthContext";
import request from "@/utils/Request";
import { useRouter } from "expo-router";
import { useContext } from "react";
import { View } from "react-native";
import { ALERT_TYPE, Toast } from "react-native-alert-notification";

export default function ScannerScreen() {
  const { organisation, warehouse } = useContext(AuthContext);
  const router = useRouter();

  const onSuccess = (response) => {
    const { model_type} = response?.data || {};
    const { id } = response?.data.model;
    if (!id || !model_type) {
      Toast.show({
        type: ALERT_TYPE.WARNING,
        title: "Invalid Scan",
        textBody: "Missing model type or ID.",
      });
      return;
    }

    switch (model_type) {
      case "Location":
        router.push(`/show-location?id=${id}`);
        break;
      case "Pallet":
        router.push(`/show-pallet?id=${id}`);
        break;
      case "Item":
        router.push(`/show-stored-item?id=${id}`);
        break;
      case "PalletDelivery":
        router.push(`/(show-delivery)/show-delivery-data?id=${id}`);
        break;
      case "PalletReturn":
        router.push(`/(show-return)/show-return-data?id=${id}`);
        break;
      default:
        Toast.show({
          type: ALERT_TYPE.WARNING,
          title: "Unsupported",
          textBody: `Model type ${model_type} is not supported yet.`,
        });
    }
  };

  const handleScan = async (code) => {
    try {
      const response = await request({
        method: "get",
        urlKey: "get-scanner",
        args: [organisation.id, warehouse.id, code],
      });

      onSuccess(response);
      return true;
    } catch (error) {
      Toast.show({
        type: ALERT_TYPE.DANGER,
        title: "Error",
        textBody: error?.data || "Failed to fetch data",
      });
      return false;
    }
  };

  return (
    <View style={{ flex: 1 }}>
      <BarcodeScanner onScan={handleScan} />
    </View>
  );
}
