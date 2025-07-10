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

const GroupItem = ({ item, selectedfulfilment, globalStyles }) => {
  const { setFulfilmentWarehouse, userData, organisation } = useContext(AuthContext);
  const isActive = selectedfulfilment?.id === item.id;
  const router = useRouter();

  const onPickFulfilment = () => {
    setFulfilmentWarehouse({
      ...userData,
      organisation,
      fulfilment: item,
      warehouse: organisation.warehouses[0],
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
      onPress={onPickFulfilment}
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

const Fulfilment = () => {
  const { userData, organisation, fulfilment } = useContext(AuthContext);
  const scheme = useColorScheme();
  const isDark = scheme === 'dark';
  const globalStyles = createGlobalStyles(isDark);

  const fulfilments = organisation?.fulfilments ?? [];

  return (
    <View style={globalStyles.container}>
      <FlatList
        data={fulfilments}
        keyExtractor={(item) => item.id.toString()}
        showsVerticalScrollIndicator={false}
        renderItem={({ item }) => (
          <GroupItem
            item={item}
            selectedfulfilment={fulfilment}
            globalStyles={globalStyles}
          />
        )}
      />
    </View>
  );
};

export default Fulfilment;
