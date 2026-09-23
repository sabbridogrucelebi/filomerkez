import axios from 'axios';
import AsyncStorage from '@react-native-async-storage/async-storage';
import * as SecureStore from 'expo-secure-store';
import { Platform, Alert, DeviceEventEmitter } from 'react-native';
import { CONFIG } from '../config';

const secureGetItem = async (key) => {
    if (Platform.OS === 'web') {
        return await AsyncStorage.getItem(key);
    } else {
        return await SecureStore.getItemAsync(key);
    }
};

// Dinamik API URL (config.js'den alınır)
const BASE_URL = Platform.OS === 'web' ? CONFIG.WEB_API_URL : CONFIG.API_BASE_URL;

// Kimlik doğrulama uçları: buradan dönen 401 "şifre yanlış" demektir,
// oturum düşmesi değil. Bu yüzden otomatik logout tetiklenmemeli.
const AUTH_ENDPOINTS = ['/login', '/forgot-password', '/reset-password'];
const isAuthEndpoint = (url = '') => AUTH_ENDPOINTS.some(e => url.startsWith(e));

const log = (...args) => { if (__DEV__) console.log(...args); };

// Aynı anda düşen birden fazla istek için tek bir bağlantı uyarısı göster.
let connectionAlertVisible = false;
const showConnectionAlertOnce = () => {
    if (connectionAlertVisible) return;
    connectionAlertVisible = true;
    Alert.alert(
        "Bağlantı Hatası",
        "Sunucuya ulaşılamıyor, lütfen ağ bağlantınızı kontrol edin.",
        [{ text: "Tamam", onPress: () => { connectionAlertVisible = false; } }],
        { onDismiss: () => { connectionAlertVisible = false; } }
    );
};

const api = axios.create({
    baseURL: BASE_URL,
    // Mobil şebekede (3G/kötü kapsama) fotoğraf ve belge yüklemeleri 10sn'yi
    // rahatça aşıyor ve kullanıcıya sahte "Bağlantı Hatası" gösteriyordu.
    timeout: 30000,
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json; charset=utf-8',
        'Bypass-Tunnel-Reminder': 'true',
        'ngrok-skip-browser-warning': 'true'
    }
});

log("Axios initialized with BASE_URL:", BASE_URL);

// Her istekten önce token'ı ekle
api.interceptors.request.use(
    async (config) => {
        const token = await secureGetItem('userToken');
        if (token) {
            log(`[API REQUEST] Token found in SecureStore. Length: ${token.length}`);
            config.headers.Authorization = `Bearer ${token}`;
        } else {
            log(`[API REQUEST] WARNING: No token found in SecureStore!`);
        }
        log(`[API REQUEST] ${config.method.toUpperCase()} ${config.url}`);
        return config;
    },
    (error) => {
        return Promise.reject(error);
    }
);

// Yanıtları (response) ve hataları merkezi olarak yönet
api.interceptors.response.use(
    (response) => {
        // İstek başarılıysa doğrudan response'u dön
        log(`[API RESPONSE] ${response.config.url} - Status: ${response.status}`);
        return response;
    },
    async (error) => {
        // Network Error veya Timeout (Sunucuya ulaşılamıyor)
        if (!error.response) {
            // İptal edilen istekler (ekran değişimi, unmount) bir bağlantı hatası değil.
            if (axios.isCancel?.(error) || error.code === 'ERR_CANCELED') {
                return Promise.reject(error);
            }
            log('[API NETWORK ERROR]', error.message);
            // Çevrimdışı açılışta paralel istekler aynı uyarıyı üst üste yığıyordu.
            showConnectionAlertOnce();
            return Promise.reject(error);
        }

        const status = error.response.status;
        const url = error.config?.url || '';

        log(`[API ERROR] ${url} - Status: ${status}`);

        // 401 Unauthorized (Token geçersiz veya süresi dolmuş)
        if (status === 401) {
            // Giriş ekranındaki hatalı şifre denemesini oturum düşmesi sanmayalım.
            if (!isAuthEndpoint(url)) {
                if (Platform.OS === 'web') {
                    await AsyncStorage.removeItem('userToken');
                } else {
                    await SecureStore.deleteItemAsync('userToken');
                }
                // Token silmek yetmiyor: AuthContext'i de haberdar etmezsek kullanıcı
                // boş/bozuk ekranda kalıyor ve uygulamayı kapatmak zorunda kalıyordu.
                Alert.alert(
                    "Oturum Sona Erdi",
                    "Güvenliğiniz için oturumunuz kapatıldı. Lütfen tekrar giriş yapın.",
                    [{ text: "Tamam" }]
                );
                DeviceEventEmitter.emit('logout');
            }
        }

        // 422 Validation Error (Form doğrulama hatası)
        else if (status === 422) {
            log('[API VALIDATION ERROR]', error.response.data.errors || error.response.data.message);
        }

        // 403 Forbidden (Yetkisiz İşlem veya Askıya Alınmış Firma)
        else if (status === 403) {
            if (error.response.data?.message === 'company_suspended') {
                if (Platform.OS === 'web') {
                    await AsyncStorage.removeItem('userToken');
                } else {
                    await SecureStore.deleteItemAsync('userToken');
                }
                Alert.alert(
                    "Erişim Engellendi",
                    "Firmanızın lisansı askıya alınmıştır. Lütfen platform yöneticisi ile iletişime geçin.",
                    [{ text: "Tamam" }]
                );
                DeviceEventEmitter.emit('logout');
            } else {
                log(`[API HTTP ERROR 403]`, error.response.data);
            }
        }

        // 500 Internal Server Error (Sunucu Hatası)
        else if (status >= 500) {
            if (__DEV__) {
                console.error('[API 500 SERVER ERROR DETAILS]', {
                    url: url,
                    message: error.response.data?.message || 'No message provided',
                    exception: error.response.data?.exception || 'Unknown exception',
                    file: error.response.data?.file || 'N/A',
                    line: error.response.data?.line || 'N/A'
                });
            }

            // Ham sunucu istisnası (dosya yolu, SQL, stack) son kullanıcıya gösterilmemeli.
            Alert.alert(
                "Sunucu Hatası",
                __DEV__ && error.response.data?.message
                    ? String(error.response.data.message).substring(0, 200)
                    : "Sunucuda beklenmeyen bir hata oluştu. Lütfen birazdan tekrar deneyin."
            );
        }
        // 402 - Session/Access expired
        else if (status === 402) {
            log('[API ACCESS EXPIRED]', error.response.data.message);
            if (Platform.OS === 'web') {
                await AsyncStorage.removeItem('userToken');
            } else {
                await SecureStore.deleteItemAsync('userToken');
            }
            Alert.alert(
                "Erişim Süresi Doldu",
                "Oturumunuzun süresi doldu. Lütfen tekrar giriş yapın veya sistem yöneticinizle iletişime geçin.",
                [{ text: "Tamam" }]
            );
            DeviceEventEmitter.emit('logout');
        }
        else {
            // Diğer hatalar (404 vb.)
            log(`[API HTTP ERROR ${status}]`, JSON.stringify(error.response.data));
        }

        return Promise.reject(error);
    }
);

export default api;
