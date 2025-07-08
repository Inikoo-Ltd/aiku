import { StyleSheet } from 'react-native';

const globalStyles = StyleSheet.create({
  container: {
    flex: 1,
    padding: 16,
    backgroundColor: '#f4f4f4',
  },

  list: {
    card: {
      padding: 16,
      backgroundColor: '#ffffff',
      shadowColor: '#000',
      shadowOpacity: 0.1,
      shadowRadius: 4,
      shadowOffset: {width: 0, height: 2},
      borderWidth: 1,
      borderColor: '#e0e0e0',
      marginBottom: 3,
    },
    container: {
      flexDirection: 'row',
      alignItems: 'center',
    },
    avatarContainer: {
      position: 'relative',
      marginRight: 12,
    },
    avatar: {
      width: 56,
      height: 56,
      borderRadius: 28,
      borderWidth: 1,
      borderColor: '#ddd',
    },
    fallbackAvatar: {
      width: 56,
      height: 56,
      borderRadius: 28,
      backgroundColor: '#5a67d8',
      justifyContent: 'center',
      alignItems: 'center',
    },
    fallbackText: {
      color: '#ffffff',
      fontSize: 20,
      fontWeight: 'bold',
    },
    statusIndicator: {
      position: 'absolute',
      bottom: 2,
      right: 2,
      width: 12,
      height: 12,
      borderRadius: 6,
      backgroundColor: '#34d399',
      borderWidth: 2,
      borderColor: '#ffffff',
    },
    textContainer: {
      flex: 1,
    },
    title: {
      fontSize: 16,
      fontWeight: 'bold',
      color: '#333',
    },
    description: {
      fontSize: 14,
      color: '#666',
      marginTop: 4,
    },

    activeCard: {
      borderWidth: 2,
      borderColor: '#4F39F6',
      backgroundColor: '#EEF2FF',
    },
    activeIndicator: {
      position: 'absolute',
      right: 10,
      top: '50%',
      transform: [{translateY: -10}],
      borderRadius: 12,
      width: 24,
      height: 24,
      alignItems: 'center',
      justifyContent: 'center',
    },
  },

  scanner: {
    centered: {
      flex: 1,
      justifyContent: 'center',
      alignItems: 'center',
    },
    scannedCodeContainer: {
      position: 'absolute',
      bottom: 50,
      alignSelf: 'center',
      backgroundColor: 'rgba(0,0,0,0.7)',
      padding: 10,
      borderRadius: 10,
    },
    scannedCodeText: {
      color: 'white',
      fontSize: 16,
    },
    buttonContainer: {
      position: 'absolute',
      top: 20,
      right: 20,
      backgroundColor: 'white',
      padding: 10,
      borderRadius: 8,
      elevation: 5,
    },
    buttonText: {
      fontSize: 14,
      fontWeight: 'bold',
    },
    fullScreenCamera: {
      position: 'absolute',
      width: '100%',
      height: '100%',
      flex: 1,
      zIndex: 100,
    },
    rnholeView: {
      alignSelf: 'center',
      alignItems: 'center',
      justifyContent: 'center',
      backgroundColor: 'rgba(0,0,0,0.5)',
    },
  },

  button_swipe_primary: {
    position: 'absolute',
    right: 0,
    top: 0,
    bottom: 0,
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 10,
    backgroundColor: '#E0E7FF',
    justifyContent: 'center',  
    marginBottom: 3,
  },

  button_swipe_danger : {
    position: 'absolute',
    left: 0,
    top: 0,
    bottom: 0,
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 10,
    backgroundColor: '#fee2e2',
    justifyContent: 'center',
    marginBottom: 3,
  }
});

export default globalStyles;