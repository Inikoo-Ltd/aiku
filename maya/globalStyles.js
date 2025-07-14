import { StyleSheet } from 'react-native';

export const createGlobalStyles = (isDark) =>
  StyleSheet.create({
    container: {
      flex: 1,
      backgroundColor: isDark ? '#111827' : '#F3F4F6', // dark:bg-gray-900 / bg-gray-100
      paddingHorizontal: 16, // px-4
      paddingVertical: 16,   // py-4
    },

    list: {
      card: {
        padding: 16,
        backgroundColor: isDark ? '#1F2937' : '#ffffff', // dark:bg-gray-800
        shadowColor: '#000',
        shadowOpacity: 0.1,
        shadowRadius: 4,
        shadowOffset: { width: 0, height: 2 },
        elevation: 2,
        borderWidth: 1,
        borderColor: isDark ? '#374151' : '#e5e7eb', // dark:border-gray-700 / border-gray-200
        marginBottom: 12, // mb-3
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
        borderColor: isDark ? '#4B5563' : '#D1D5DB', // dark:border-gray-600 / border-gray-300
      },

      fallbackAvatar: {
        width: 56,
        height: 56,
        borderRadius: 28,
        backgroundColor: '#6366F1', // indigo-500
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
        backgroundColor: '#10B981', // emerald-500
        borderWidth: 2,
        borderColor: isDark ? '#1F2937' : '#ffffff', // dark:bg-gray-800
      },

      textContainer: {
        flex: 1,
      },

      title: {
        fontSize: 16,
        fontWeight: 'bold',
        color: isDark ? '#F9FAFB' : '#111827', // dark:text-gray-100 / text-gray-900
      },

      description: {
        fontSize: 14,
        color: isDark ? '#9CA3AF' : '#6B7280', // dark:text-gray-400 / text-gray-500
        marginTop: 4,
      },

      activeCard: {
        borderWidth: 2,
        borderColor: '#4F46E5', // indigo-600
      },

      activeIndicator: {
        position: 'absolute',
        right: 10,
        top: '50%',
        transform: [{ translateY: -10 }],
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
        backgroundColor: isDark ? '#1F2937' : '#ffffff', // dark:bg-gray-800
        padding: 10,
        borderRadius: 8,
        elevation: 5,
      },

      buttonText: {
        fontSize: 14,
        fontWeight: 'bold',
        color: isDark ? '#F9FAFB' : '#111827',
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
      backgroundColor: isDark ? '#3730A3' : '#E0E7FF', // dark:indigo-800 / indigo-100
      justifyContent: 'center',
      marginBottom: 3,
      paddingVertical : 38,
    },

    button_swipe_danger: {
      position: 'absolute',
      left: 0,
      top: 0,
      bottom: 0,
      flexDirection: 'row',
      alignItems: 'center',
      paddingHorizontal: 10,
      paddingVertical : 38,
      backgroundColor: isDark ? '#991B1B' : '#FEE2E2', // dark:red-800 / red-100
      justifyContent: 'center',
      marginBottom: 3,
    },
  });
