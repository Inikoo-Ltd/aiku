import BaseList from '@/components/BaseList';
import CardListItem from '@/components/CardListItem';
import { AuthContext } from '@/components/context/AuthContext';
import { createGlobalStyles } from '@/globalStyles';
import { useRouter } from 'expo-router';
import { useContext } from 'react';
import { View, useColorScheme } from 'react-native';

const StockDeliveries = () => {
  const { organisation, warehouse } = useContext(AuthContext);
  const scheme = useColorScheme();
  const isDark = scheme === 'dark';
  const globalStyles = createGlobalStyles(isDark);
  const router = useRouter();

  return (
    <View style={globalStyles.container}>
      <BaseList
        urlKey="get-stock-deliveries"
        args={[organisation.id, warehouse.id]}
        listItem={({ item }) => (
          <CardListItem
            title={item.reference}
            subtitle={item.slug || 'No description available'}
            onPress={() => router.push(`/show-stock-delivery?id=${item.id}`)}
          />
        )}
      />
    </View>
  );
};

export default StockDeliveries;
