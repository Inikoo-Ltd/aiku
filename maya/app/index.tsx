import { getData } from '@/utils/AsyncStorage';
import { useRouter } from 'expo-router';
import { useEffect, useState } from 'react';
import { ActivityIndicator, View } from 'react-native';

export default function Index() {
  const router = useRouter();
  const [isChecking, setIsChecking] = useState(true);

  useEffect(() => {
    const checkAuth = async () => {
      const user = await getData('persist:user'); // ✅ correct usage
      console.log('User from storage:', user);

      if (user?.token) {
        router.replace('/home'); // ✅ logged in
      } else {
        router.replace('/manual-login'); // ❌ not logged in
      }

      setIsChecking(false);
    };

    checkAuth();
  }, []);

  // Optional: Splash while checking
  if (isChecking) {
    return (
      <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center' }}>
        <ActivityIndicator size="large" />
      </View>
    );
  }

  return null;
}
