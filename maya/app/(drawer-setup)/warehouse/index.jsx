import { AuthContext } from '@/components/context/AuthContext';
import globalStyles from '@/globalStyles';
import { faCheckCircle } from '@fortawesome/free-solid-svg-icons/faCheckCircle';
import { FontAwesomeIcon } from '@fortawesome/react-native-fontawesome';
import { useRouter } from 'expo-router';
import { useContext } from 'react';
import { FlatList, Text, TouchableOpacity, View } from 'react-native';

const GroupItem = ({ item, navigation, selectedWarehouse }) => {
  const { setFulfilmentWarehouse, userData, organisation } = useContext(AuthContext);
  const isActive = selectedWarehouse?.id === item.id;
  const router =  useRouter()
  const onPickWarehouse = () => {
    setFulfilmentWarehouse({ ...userData, warehouse : item , organisation : organisation})
    router.replace('/(drawer-setup)/home')
  }

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
          <FontAwesomeIcon icon={faCheckCircle} color='green'/>
        </View>
      )}
    </View>
  </TouchableOpacity>
  );
};

const Warehouse = ({ navigation }) => {
  const { userData, organisation, fulfilment, warehouse } = useContext(AuthContext);
  

  return (
    <View style={globalStyles.container}>
      <FlatList
        data={organisation?.warehouses ? organisation?.warehouses : []}
        keyExtractor={(item) => item.id.toString()}
        showsVerticalScrollIndicator={false}
        renderItem={({ item }) => <GroupItem item={item} navigation={navigation} selectedWarehouse={warehouse}/>}
      />
    </View>
  );
};


export default Warehouse;