import BaseList from '@/components/BaseList';
import { AuthContext } from '@/components/context/AuthContext';
import OrgStockItem from '@/components/OrgStockItem';
import { createGlobalStyles } from '@/globalStyles';
import { useRouter } from 'expo-router';
import { useContext } from 'react';
import { View, useColorScheme } from 'react-native';

const OrgStocksScreens = () => {
  const { organisation, warehouse } = useContext(AuthContext);
  const scheme = useColorScheme();
  const isDark = scheme === 'dark';
  const globalStyles = createGlobalStyles(isDark);
  const router = useRouter()

  return (
    <View style={globalStyles.container}>
      <BaseList
        urlKey="get-org-stocks"
        args={[organisation.id, warehouse.id]}
        listItem={({ item }) => (
          <OrgStockItem
            item={item}
            onPress={() => router.push(`/show-org-stock?id=${item.id}`)}
          />
        )}
      />
    </View>
  );
};

export default OrgStocksScreens;
