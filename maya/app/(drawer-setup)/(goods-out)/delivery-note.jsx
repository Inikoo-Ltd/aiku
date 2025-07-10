// screens/DeliveryNotes.tsx

import BaseList from '@/components/BaseList';
import { AuthContext } from '@/components/context/AuthContext';
import DeliveryNoteItem from '@/components/DeliveryNoteItem';
import { createGlobalStyles } from '@/globalStyles';
import { useRouter } from 'expo-router';
import { useContext } from 'react';
import { View, useColorScheme } from 'react-native';

const DeliveryNotes = () => {
  const { organisation, warehouse } = useContext(AuthContext);
  const router = useRouter();
  const scheme = useColorScheme();
  const isDark = scheme === 'dark';
  const globalStyles = createGlobalStyles(isDark);

  return (
    <View style={globalStyles.container}>
      <BaseList
        urlKey="get-delivery-notes"
        args={[organisation.id, warehouse.id]}
        listItem={({ item }) => (
          <DeliveryNoteItem
            item={item}
            onPress={() => router.push(`/show-delivery-note?id=${item.id}`)}
          />
        )}
      />
    </View>
  );
};

export default DeliveryNotes;
