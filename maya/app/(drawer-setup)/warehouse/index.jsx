import { AuthContext } from '@/components/context/AuthContext';
import { createGlobalStyles } from '@/globalStyles';
import { faCheckCircle } from '@fortawesome/free-solid-svg-icons/faCheckCircle';
import { FontAwesomeIcon } from '@fortawesome/react-native-fontawesome';
import { useRouter } from 'expo-router';
import { useContext } from 'react';
import {
  FlatList,
  Text,
  TouchableOpacity,
  View,
  useColorScheme,
} from 'react-native';

const GroupItem = ({ item, selectedWarehouse, globalStyles }) => {
  const { setFulfilmentWarehouse, userData, organisation } = useContext(AuthContext);
  const isActive = selectedWarehouse?.id === item.id;
  const router = useRouter();

  const onPickWarehouse = () => {
    setFulfilmentWarehouse({
      ...userData,
      organisation,
      warehouse: item,
    });
    router.replace('/(drawer-setup)/home');
  };

  return (
    <TouchableOpacity
      style={[
        globalStyles.list.card,
        isActive && globalStyles.list.activeCard,
      ]}
      activeOpacity={0.7}
      onPress={onPickWarehouse}
    >
      <View style={globalStyles.list.container}>
        <View style={globalStyles.list.textContainer}>
          <Text style={globalStyles.list.title}>{item.name}</Text>
        </View>

        {isActive && (
          <View style={globalStyles.list.activeIndicator}>
            <FontAwesomeIcon icon={faCheckCircle} color="green" />
          </View>
        )}
      </View>
    </TouchableOpacity>
  );
};

const Warehouse = () => {
  const { organisation, warehouse } = useContext(AuthContext);
  const scheme = useColorScheme();
  const isDark = scheme === 'dark';
  const globalStyles = createGlobalStyles(isDark);

  const warehouseList = organisation?.warehouses ?? [];

  return (
    <View style={globalStyles.container}>
      <FlatList
        data={warehouseList}
        keyExtractor={(item) => item.id.toString()}
        showsVerticalScrollIndicator={false}
        renderItem={({ item }) => (
          <GroupItem
            item={item}
            selectedWarehouse={warehouse}
            globalStyles={globalStyles}
          />
        )}
      />
    </View>
  );
};

export default Warehouse;
