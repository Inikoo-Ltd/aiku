import { AuthContext } from "@/components/context/AuthContext";
import {
  faCashRegister,
  faCircleQuestion,
  faPeopleArrows,
  faSignOutAlt,
  faWarehouse,
} from "@/private/fa/pro-regular-svg-icons";
import { FontAwesomeIcon } from "@fortawesome/react-native-fontawesome";
import {
  DrawerContentScrollView,
  DrawerItem,
} from "@react-navigation/drawer";
import { useContext, useState } from "react";
import {
  Image,
  Text,
  TouchableOpacity,
  useColorScheme,
  View,
} from "react-native";

export default function CustomDrawer(props) {
  const { signOut, userData, warehouse, organisation } = useContext(AuthContext);
  const [imageError, setImageError] = useState(false);
  const colorScheme = useColorScheme();
  const isDark = colorScheme === "dark";

  const currentRoute = props.state.routeNames[props.state.index];

  const handleImageError = () => setImageError(true);

  const getInitials = (name) => {
    if (!name) return "?";
    return name
      .split(" ")
      .map((word) => word[0])
      .join("")
      .toUpperCase();
  };

  return (
    <View className={`flex-1 ${isDark ? "bg-gray-900" : "bg-white"}`}>
      <DrawerContentScrollView
        {...props}
        contentContainerStyle={{ padding: 0 }}
        className={isDark ? "bg-indigo-700" : "bg-indigo-500"}
      >
        {/* Profile */}
        <View className="p-5 items-center">
          <TouchableOpacity onPress={() => props.navigation.navigate("setting")}>
            {!imageError && userData?.image?.original ? (
              <Image
                source={{ uri: userData.image.original }}
                className="w-20 h-20 rounded-full mb-2"
                onError={handleImageError}
              />
            ) : (
              <View className="w-20 h-20 rounded-full bg-white items-center justify-center mb-2">
                <Text className="text-xl font-bold text-gray-700">
                  {getInitials(userData?.username)}
                </Text>
              </View>
            )}
          </TouchableOpacity>

          <Text className="text-white text-lg font-medium mb-1">
            {userData?.username || "Guest User"}
          </Text>

          {!!organisation?.label && (
            <View className="flex-row items-center gap-2">
              <FontAwesomeIcon
                icon={organisation.type === "shop" ? faCashRegister : faPeopleArrows}
                size={14}
                color="#fff"
              />
              <Text className="text-white text-lg font-medium">
                {organisation.label}
              </Text>
            </View>
          )}
        </View>

        {/* Drawer items */}
        <View
          className={`${
            warehouse
              ? "bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md m-2 relative mt-10 pt-6"
              : "bg-white dark:bg-gray-800 pt-2"
          }`}
        >
          {warehouse && (
            <>
              <View className="absolute top-0 left-0 w-full h-[2px] bg-gray-300 dark:bg-gray-600" />
              <View className="absolute -top-5 left-4 bg-white dark:bg-gray-800 px-3 py-1 rounded-md border border-gray-300 dark:border-gray-600 flex-row items-center gap-2">
                <FontAwesomeIcon icon={faWarehouse} size={18} color={isDark ? "#fff" : "#000"} />
                <Text className="text-lg font-bold text-black dark:text-white">
                  {warehouse.label || warehouse.name}
                </Text>
              </View>
            </>
          )}

          {props.state.routes.map((route) => {
            const descriptor = props.descriptors?.[route.key];
            const show = descriptor?.options?.showInDrawer ?? true;
            if (!show) return null;

            const title =
              descriptor?.options?.title ||
              route.name.replace("/index", "").replace("-", " ");
            const isActive = currentRoute === route.name;

            const IconComponent =
              descriptor?.options?.drawerIcon?.({
                color: isActive ? "#fff" : isDark ? "#fff" : "#000",
                size: 20,
                focused: isActive,
              }) || (
                <FontAwesomeIcon
                  icon={faCircleQuestion}
                  size={20}
                  color={isActive ? "#fff" : isDark ? "#fff" : "#000"}
                />
              );

            return (
              <DrawerItem
                key={route.key}
                label={title}
                icon={() => IconComponent}
                onPress={() => props.navigation.navigate(route.name)}
                style={{
                  backgroundColor: isActive
                    ? isDark
                      ? "#4f46e5"
                      : "#6366f1"
                    : "transparent",
                  borderRadius: 0,
                }}
                labelStyle={{
                  color: isActive ? "#fff" : isDark ? "#fff" : "#000",
                }}
              />
            );
          })}
        </View>
      </DrawerContentScrollView>

      {/* Sign Out */}
      <View className="p-5 border-t border-gray-300 dark:border-gray-600">
        <TouchableOpacity onPress={signOut} className="py-3 flex-row items-center">
          <FontAwesomeIcon icon={faSignOutAlt} size={22} color={isDark ? "#fff" : "#000"} />
          <Text className="text-base font-medium ml-2 text-black dark:text-white">
            Sign Out
          </Text>
        </TouchableOpacity>
      </View>
    </View>
  );
}
