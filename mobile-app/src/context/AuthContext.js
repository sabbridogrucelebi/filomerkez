import React, { createContext, useState, useEffect, useRef } from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';
import * as SecureStore from 'expo-secure-store';
import { DeviceEventEmitter, Platform, Vibration } from 'react-native';
import { Audio } from 'expo-av';
import * as Notifications from 'expo-notifications';
import * as Device from 'expo-device';
import Constants from 'expo-constants';
import api from '../api/axios';
import StylishNotification from '../components/StylishNotification';

Notifications.setNotificationHandler({
    handleNotification: async () => ({
        shouldShowAlert: true, // Uygulama açıkken de sistem bildirimi (WhatsApp gibi) düşsün
        shouldPlaySound: true,
        shouldSetBadge: true,
    }),
});

async function registerForPushNotificationsAsync() {
    let token;

    if (Platform.OS === 'android') {
        await Notifications.setNotificationChannelAsync('default', {
            name: 'default',
            importance: Notifications.AndroidImportance.MAX,
            vibrationPattern: [0, 250, 250, 250],
            lightColor: '#FF231F7C',
        });
    }

    if (Device.isDevice) {
        const { status: existingStatus } = await Notifications.getPermissionsAsync();
        let finalStatus = existingStatus;
        if (existingStatus !== 'granted') {
            const { status } = await Notifications.requestPermissionsAsync();
            finalStatus = status;
        }
        if (finalStatus !== 'granted') {
            console.log('Failed to get push token for push notification!');
            return;
        }
        
        try {
            const projectId = Constants?.expoConfig?.extra?.eas?.projectId ?? Constants?.easConfig?.projectId;
            token = (await Notifications.getExpoPushTokenAsync({ projectId })).data;
        } catch(e) {
            console.log('Project ID ile token alınamadı, normal deneniyor...');
            try {
                token = (await Notifications.getExpoPushTokenAsync()).data;
            } catch(expoGoError) {
                console.log('Expo Go üzerinde Push Token desteklenmiyor. Uygulama normal devam ediyor.', expoGoError.message);
                token = null;
            }
        }
    }

    return token;
}

export const AuthContext = createContext();

// Helper to safely use SecureStore on native, and AsyncStorage on web
const secureSetItem = async (key, value) => {
    if (Platform.OS === 'web') {
        await AsyncStorage.setItem(key, value);
    } else {
        await SecureStore.setItemAsync(key, value);
    }
};

const secureGetItem = async (key) => {
    if (Platform.OS === 'web') {
        return await AsyncStorage.getItem(key);
    } else {
        return await SecureStore.getItemAsync(key);
    }
};

const secureDeleteItem = async (key) => {
    if (Platform.OS === 'web') {
        await AsyncStorage.removeItem(key);
    } else {
        await SecureStore.deleteItemAsync(key);
    }
};

export const AuthProvider = ({ children }) => {
    const [isLoading, setIsLoading] = useState(false);
    const [isInitializing, setIsInitializing] = useState(true);
    const [userToken, setUserToken] = useState(null);
    const [userInfo, setUserInfo] = useState(null);
    const [showTransition, setShowTransition] = useState(false);
    const [unreadChatCount, setUnreadChatCount] = useState(0);
    const unreadCountRef = useRef(0);
    const notificationRef = useRef(null);

    const playNotificationSound = async () => {
        try {
            if (Platform.OS !== 'web') Vibration.vibrate();
            const { sound } = await Audio.Sound.createAsync(
                { uri: 'https://actions.google.com/sounds/v1/alarms/beep_short.ogg' },
                { shouldPlay: true }
            );
            setTimeout(() => { sound.unloadAsync(); }, 2000);
        } catch (e) {
            console.error('Sound play error', e);
        }
    };

    const checkUnreadMessages = async () => {
        if (!userToken) return;
        try {
            const res = await api.get('/chat/unread');
            const newCount = res.data.count;
            if (newCount > unreadCountRef.current) {
                playNotificationSound();
            }
            unreadCountRef.current = newCount;
            setUnreadChatCount(newCount);
        } catch (e) {
            // Ignore small errors during polling
        }
    };

    const hasPermission = (perm) => {
        if (userInfo?.is_company_admin) return true;
        return userInfo?.permissions?.includes(perm) ?? false;
    };

    const hasRole = (role) => {
        return userInfo?.role === role;
    };

    const login = async (email, password) => {
        setIsLoading(true);
        try {
            const response = await api.post('/login', {
                email,
                password,
                device_name: Platform.OS === 'web' ? 'web' : 'mobile-app'
            });

            if (response.data.token) {
                // Save to storage FIRST to prevent race conditions in useEffects/axios
                await AsyncStorage.setItem('userInfo', JSON.stringify(response.data.user));
                await secureSetItem('userToken', response.data.token);
                
                // Then update React state
                setUserInfo(response.data.user);
                setUserToken(response.data.token);
                setShowTransition(true); // Trigger transition screen
                
                // Update Push Token
                if (Platform.OS !== 'web') {
                    const token = await registerForPushNotificationsAsync();
                    if (token) {
                        try { await api.post('/user/push-token', { push_token: token }); } catch (e) { console.log('Push Token API error', e.response?.data || e.message); }
                    }
                }
            }
        } catch (error) {
            console.log('Login error', error.response?.data || error.message);
            throw error;
        } finally {
            setIsLoading(false);
        }
    };

    const logout = async () => {
        setIsLoading(true);
        try {
            await api.post('/logout');
        } catch (e) {
            console.log(e);
        }
        setUserToken(null);
        setUserInfo(null);
        await AsyncStorage.removeItem('userInfo');
        await secureDeleteItem('userToken');
        setIsLoading(false);
    };

    const isLoggedIn = async () => {
        try {
            // Sadece native platformlarda eski AsyncStorage token'ı SecureStore'a taşı (migration)
            if (Platform.OS !== 'web') {
                let oldToken = await AsyncStorage.getItem('userToken');
                if (oldToken) {
                    await SecureStore.setItemAsync('userToken', oldToken);
                    await AsyncStorage.removeItem('userToken');
                }
            }

            let userInfoStr = await AsyncStorage.getItem('userInfo');
            let token = await secureGetItem('userToken');
            
            if (userInfoStr && token) {
                let parsedUser = JSON.parse(userInfoStr);
                setUserInfo(parsedUser);
                setUserToken(token);
                
                // Token doğrulaması yap
                try {
                    const res = await api.get('/me');
                    setUserInfo(res.data.user);
                    await AsyncStorage.setItem('userInfo', JSON.stringify(res.data.user));

                    // Update Push Token on app start
                    if (Platform.OS !== 'web') {
                        const pushToken = await registerForPushNotificationsAsync();
                        if (pushToken) {
                            try { await api.post('/user/push-token', { push_token: pushToken }); } catch (e) { }
                        }
                    }
                } catch (e) {
                    // Token geçersizse çıkış yap
                    logout();
                }
            }
        } catch (e) {
            console.log(e);
        } finally {
            setIsInitializing(false);
        }
    };

    const refreshMe = async () => {
        try {
            const res = await api.get('/me');
            setUserInfo(res.data.user);
            await AsyncStorage.setItem('userInfo', JSON.stringify(res.data.user));
        } catch (e) {
            console.error('refreshMe error', e);
        }
    };

    useEffect(() => {
        isLoggedIn();

        let interval;
        if (userToken) {
            checkUnreadMessages();
            interval = setInterval(checkUnreadMessages, 5000);
        }

        const logoutListener = DeviceEventEmitter.addListener('logout', () => {
            setUserToken(null);
            setUserInfo(null);
            AsyncStorage.removeItem('userInfo');
            secureDeleteItem('userToken');
        });

        const notificationListener = Notifications.addNotificationReceivedListener(notification => {
            // Sistem banner'ı (shouldShowAlert: true) kullanıldığı için uygulama içi özel bildirime (StylishNotification) gerek yok.
        });

        // Intercept headers for permissions update
        const interceptorId = api.interceptors.response.use((response) => {
            if (response.headers['x-permissions-updated-at']) {
                const headerTime = response.headers['x-permissions-updated-at'];
                setUserInfo(prev => {
                    if (!prev || !prev.permissions_updated_at || prev.permissions_updated_at !== headerTime) {
                        // Background refresh
                        refreshMe();
                    }
                    return prev;
                });
            }
            return response;
        });

        return () => {
            logoutListener.remove();
            api.interceptors.response.eject(interceptorId);
            if (interval) clearInterval(interval);
            if (notificationListener) notificationListener.remove();
        };
    }, [userToken]); // Include userToken so interval uses it, but interceptor re-binds. Better yet, we should separate them.

    return (
        <AuthContext.Provider value={{ login, logout, isLoading, isInitializing, userToken, userInfo, hasPermission, hasRole, showTransition, setShowTransition, refreshMe, unreadChatCount }}>
            {children}
            <StylishNotification ref={notificationRef} />
        </AuthContext.Provider>
    );
};
