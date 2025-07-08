import { AuthContext } from '@/components/context/AuthContext'; // or from `context.js` if re-exported
import React, { useContext } from 'react';
import { Button, StyleSheet, Text, View } from 'react-native';

const HomeScreen = () => {
  const { userData, signOut } = useContext(AuthContext);

  return (
    <View style={styles.container}>
      <Text style={styles.welcomeText}>
        Welcome, {userData?.name || userData?.username || 'User'}!
      </Text>
      <Button title="Logout" onPress={signOut} />
    </View>
  );
};

export default HomeScreen;

const styles = StyleSheet.create({
  container: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 16,
    backgroundColor: '#eef',
  },
  welcomeText: {
    fontSize: 20,
    marginBottom: 20,
  },
});
