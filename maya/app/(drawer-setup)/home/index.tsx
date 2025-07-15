import { AuthContext } from '@/components/context/AuthContext';
import { createGlobalStyles } from '@/globalStyles';
import { useColorScheme } from '@/hooks/useColorScheme';
import { faQrcode } from '@/private/fa/pro-light-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-native-fontawesome';
import { useRouter } from 'expo-router';
import React, { useContext } from 'react';
import { StyleSheet, Text, TouchableOpacity, View } from 'react-native';

const HomeScreen = () => {
  const { userData, signOut, organisation, warehouse } = useContext(AuthContext);
  const colorScheme = useColorScheme();
  const isDark = colorScheme === 'dark';
  const global = createGlobalStyles(isDark);
  const router = useRouter();

  const org = organisation || { name: 'Not set' };
  const wh = warehouse || { name: 'Not Set' };

  return (
    <View style={global.container}>
      <Text style={[styles.title, { color: isDark ? '#F9FAFB' : '#111827' }]}>
        Welcome, {userData?.name || userData?.username || 'User'}!
      </Text>

      <View style={[styles.card, {
        backgroundColor: isDark ? '#1F2937' : '#ffffff',
        borderColor: isDark ? '#374151' : '#e5e7eb',
        paddingTop : 4
      }]}>
        <Text style={[styles.label, { color: isDark ? '#9CA3AF' : '#6B7280' }]}>Organisation</Text>
        <Text style={[styles.value, { color: isDark ? '#F9FAFB' : '#111827' }]}>{org.name}</Text>

        <Text style={[styles.label, { color: isDark ? '#9CA3AF' : '#6B7280' }]}>Warehouse</Text>
        <Text style={[styles.value, { color: isDark ? '#F9FAFB' : '#111827' }]}>{wh.name}</Text>
      </View>

      <TouchableOpacity
        style={[styles.button, { backgroundColor: isDark ? '#3730A3' : '#4F46E5' }]}
        onPress={() => router.push('/scanner')}
      >
        <FontAwesomeIcon icon={faQrcode} size={18} color="#fff" style={styles.icon} />
        <Text style={styles.buttonText}>Go to Scanner</Text>
      </TouchableOpacity>

      {/* <TouchableOpacity
        style={[styles.button, { backgroundColor: isDark ? '#991B1B' : '#DC2626' }]}
        onPress={signOut}
      >
        <FontAwesomeIcon icon={faSignOutAlt} size={18} color="#fff" style={styles.icon} />
        <Text style={styles.buttonText}>Logout</Text>
      </TouchableOpacity> */}
    </View>
  );
};

export default HomeScreen;

const styles = StyleSheet.create({
  title: {
    fontSize: 22,
    fontWeight: 'bold',
    marginBottom: 20,
    textAlign: 'center',
  },
  card: {
    padding: 16,
    borderRadius: 12,
    borderWidth: 1,
    marginBottom: 24,
  },
  label: {
    fontSize: 14,
    marginTop: 12,
  },
  value: {
    fontSize: 16,
    fontWeight: '600',
  },
  button: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 12,
    paddingHorizontal: 20,
    borderRadius: 10,
    marginBottom: 12,
  },
  icon: {
    marginRight: 10,
  },
  buttonText: {
    color: '#fff',
    fontWeight: '600',
    fontSize: 16,
  },
});
