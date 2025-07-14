import BaseList from '@/components/BaseList';
import { AuthContext } from '@/components/context/AuthContext';
import StoredItemsItem from '@/components/StoredItemsItem';
import { createGlobalStyles } from '@/globalStyles';
import { useRouter } from 'expo-router'; // Uncomment if you want navigation
import { useContext } from 'react';
import { View, useColorScheme } from 'react-native';

const StoredItems = () => {
  const { organisation, warehouse } = useContext(AuthContext);
  const scheme = useColorScheme();
  const isDark = scheme === 'dark';
  const globalStyles = createGlobalStyles(isDark);
  const router = useRouter(); // Uncomment if using router.push()

  return (
    <View style={globalStyles.container}>
      <BaseList
        urlKey="get-stored-items"
        args={[organisation.id, warehouse.id]}
        listItem={({ item }) => (
          <StoredItemsItem
            icon={item.state_icon.icon}
            iconColor={item.state_icon.color}
            title={item.reference}
            subtitle={item.customer_name || 'No customer available'}
            onPress={() => router.push(`/show-stored-item?id=${item.id}`)}
          />
        )}
      />
    </View>
  );
};

export default StoredItems;
