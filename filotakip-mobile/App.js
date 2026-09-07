import React, { useState, useEffect, useRef } from 'react';
import { View, StyleSheet, Animated, Dimensions, Text, TextInput, TouchableOpacity, ScrollView, ActivityIndicator, KeyboardAvoidingView, Platform, ImageBackground, Easing, Image, Keyboard, Modal } from 'react-native';
import { StatusBar, setStatusBarStyle } from 'expo-status-bar';
import { MaterialIcons, FontAwesome5 } from '@expo/vector-icons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import MapView, { Marker, Callout, CalloutSubview, PROVIDER_GOOGLE, Polyline } from 'react-native-maps';
import * as Location from 'expo-location';
import { WebView } from 'react-native-webview';
import DateTimePicker from '@react-native-community/datetimepicker';

var W = Dimensions.get('window').width;
var H = Dimensions.get('window').height;

// ==============================
// 1. NEON 3D HARİTALI SPLASH EKRANI (Gerçek Görsel)
// ==============================
function GpsCarMarker() {
    var pulseAnim1 = useRef(new Animated.Value(0)).current;
    var pulseAnim2 = useRef(new Animated.Value(0)).current;

    useEffect(function() {
        var animate = (anim, delay) => {
            Animated.loop(
                Animated.timing(anim, {
                    toValue: 1,
                    duration: 2500,
                    delay: delay,
                    easing: Easing.out(Easing.ease),
                    useNativeDriver: true,
                })
            ).start();
        };
        animate(pulseAnim1, 0);
        animate(pulseAnim2, 800);
    }, []);

    var getStyle = (anim) => ({
        transform: [{ scale: anim.interpolate({ inputRange: [0, 1], outputRange: [1, 4] }) }],
        opacity: anim.interpolate({ inputRange: [0, 1], outputRange: [0.8, 0] })
    });

    return (
        <View style={styles.carMarkerContainer}>
            {/* GPS Dalgaları (Yeni görseldeki muazzam 3D aracın altına yerleşir) */}
            <Animated.View style={[styles.pulseRing, getStyle(pulseAnim1)]} />
            <Animated.View style={[styles.pulseRing, getStyle(pulseAnim2)]} />
        </View>
    );
}

function SplashScreen(props) {
    var fadeAnim = useRef(new Animated.Value(0)).current;

    useEffect(function() {
        // Yumuşakça belirme
        Animated.timing(fadeAnim, { toValue: 1, duration: 1500, useNativeDriver: true }).start();

        // 4 saniye sonra Login ekranına geçiş
        var timer = setTimeout(function() {
            Animated.timing(fadeAnim, { toValue: 0, duration: 500, useNativeDriver: true }).start(function() {
                props.onFinish();
            });
        }, 4000);

        return function() { clearTimeout(timer); };
    }, []);

    return (
        <Animated.View style={[styles.fullScreen, { opacity: fadeAnim }]}>
            
            {/* Üst Kısım: Arvento Tarzı Sade ve Kalın Neon Yazı Logosu */}
            <View style={styles.splashTop}>
                <View style={{ flexDirection: 'row', alignItems: 'baseline' }}>
                    <Text style={styles.logoFilo}>filo</Text>
                    <Text style={styles.logoTakip}>takip</Text>
                </View>
                <Text style={styles.logoSub}>Mobil Sistemleri</Text>
            </View>

            {/* Yükleniyor Yazısı (Alt Kısım) */}
            <View style={styles.splashBottomText}>
                <ActivityIndicator size="small" color="#FF7300" style={{ marginBottom: 12 }} />
                <Text style={styles.loadingText}>SİSTEM BAŞLATILIYOR...</Text>
            </View>
            
        </Animated.View>
    );
}

// ==============================
// 2. NEON GİRİŞ EKRANI (Transparan Form)
// ==============================
function LoginScreen(props) {
    var fadeAnim = useRef(new Animated.Value(0)).current;
    var slideAnim = useRef(new Animated.Value(20)).current;
    
    var emailState = useState('');
    var email = emailState[0];
    var setEmail = emailState[1];

    var passState = useState('');
    var password = passState[0];
    var setPassword = passState[1];

    var secureState = useState(true);
    var secureText = secureState[0];
    var setSecureText = secureState[1];

    var loadState = useState(false);
    var isLoading = loadState[0];
    var setIsLoading = loadState[1];

    useEffect(function() {
        Animated.parallel([
            Animated.timing(fadeAnim, { toValue: 1, duration: 800, useNativeDriver: true }),
            Animated.spring(slideAnim, { toValue: 0, tension: 30, friction: 8, useNativeDriver: true })
        ]).start();
    }, []);

    function handleLogin() {
        if (!email || !password) {
            alert('Lütfen e-posta ve şifrenizi girin.');
            return;
        }
        setIsLoading(true);
        
        fetch('https://mehmetcelebiturizm.com/app/api/v1/login', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ email: email, password: password, device_name: 'MobileApp' })
        })
        .then(response => response.json())
        .then(data => {
            setIsLoading(false);
            if (data.token) {
                props.onLoginSuccess(data.token);
            } else {
                alert(data.message || 'Giriş başarısız. Lütfen bilgilerinizi kontrol edin.');
            }
        })
        .catch(error => {
            setIsLoading(false);
            alert('Sunucuya bağlanılamadı. Lütfen internet bağlantınızı kontrol edin.');
        });
    }

    return (
        <KeyboardAvoidingView behavior={Platform.OS === "ios" ? "padding" : "height"} style={{ flex: 1 }}>
            <ScrollView contentContainerStyle={styles.loginScroll} keyboardShouldPersistTaps="handled">
                
                <Animated.View style={{ opacity: fadeAnim, transform: [{ translateY: slideAnim }] }}>
                    
                    {/* Arvento Tarzı Sade ve Kalın Neon Yazı Logosu */}
                    <View style={styles.loginHeader}>
                        <View style={{ flexDirection: 'row', alignItems: 'baseline' }}>
                            <Text style={styles.logoFiloSmall}>filo</Text>
                            <Text style={styles.logoTakipSmall}>takip</Text>
                        </View>
                        <Text style={styles.logoSubSmall}>Mobil Sistemleri</Text>
                    </View>

                    {/* Yarı Şeffaf Neon Form Kartı (Arka plandaki şehri kapatmaz) */}
                    <View style={styles.neonCard}>
                        
                        <View style={styles.inputGroup}>
                            <Text style={styles.inputLabel}>E-POSTA VEYA KULLANICI ADI</Text>
                            <View style={styles.inputBox}>
                                <FontAwesome5 name="user-alt" size={16} color="#FF7300" style={styles.inputIconBase} />
                                <TextInput
                                    style={styles.inputDark}
                                    placeholder="Kullanıcı Adı: 1"
                                    placeholderTextColor="#64748B"
                                    value={email}
                                    onChangeText={setEmail}
                                    autoCapitalize="none"
                                />
                            </View>
                        </View>

                        <View style={styles.inputGroup}>
                            <Text style={styles.inputLabel}>ŞİFRE</Text>
                            <View style={styles.inputBox}>
                                <FontAwesome5 name="lock" size={16} color="#FF7300" style={styles.inputIconBase} />
                                <TextInput
                                    style={styles.inputDark}
                                    placeholder="Şifre: 1"
                                    placeholderTextColor="#64748B"
                                    value={password}
                                    onChangeText={setPassword}
                                    secureTextEntry={secureText}
                                />
                                <TouchableOpacity onPress={function() { setSecureText(!secureText); }} style={styles.eyeBtn}>
                                    <MaterialIcons name={secureText ? "visibility" : "visibility-off"} size={20} color="#64748B" />
                                </TouchableOpacity>
                            </View>
                        </View>

                        {/* Neon Login Butonu */}
                        <TouchableOpacity style={styles.neonBtn} onPress={handleLogin} activeOpacity={0.8} disabled={isLoading}>
                            {isLoading ? (
                                <ActivityIndicator color="#020617" />
                            ) : (
                                <Text style={styles.neonBtnText}>GİRİŞ YAP</Text>
                            )}
                        </TouchableOpacity>

                        <TouchableOpacity style={styles.forgotPass}>
                            <Text style={styles.forgotText}>Şifremi Unuttum</Text>
                        </TouchableOpacity>

                    </View>
                </Animated.View>
            </ScrollView>
        </KeyboardAvoidingView>
    );
}

// ==============================
// 3. CANLI HARİTA EKRANI (Native Map)
// ==============================
function MapScreen({ token }) {
    var mapRef = useRef(null);
    var historyMapRef = useRef(null);
    var webViewRef = useRef(null);
    var [vehicles, setVehicles] = useState([]);
    var [isFetching, setIsFetching] = useState(false);
    var [activeStreetViewId, setActiveStreetViewId] = useState(null);
    var [selectedMarkerId, setSelectedMarkerId] = useState(null);
    
    // Sürekli Odaklanılan Araç
    var [focusedVehicleId, setFocusedVehicleId] = useState(null);
    var isInitialFocusDone = useRef(false);

    // Arama States
    var [searchQuery, setSearchQuery] = useState('');
    var [isSearching, setIsSearching] = useState(false);
    var [searchResults, setSearchResults] = useState(null);

    // Geçmiş Modu States
    var [isHistoryMode, setIsHistoryMode] = useState(false);
    var [historyData, setHistoryData] = useState([]);
    var [tripsData, setTripsData] = useState([]);
    var [startAddress, setStartAddress] = useState('Yükleniyor...');
    var [endAddress, setEndAddress] = useState('Yükleniyor...');
    var [playbackIndex, setPlaybackIndex] = useState(0);
    var [isPlaying, setIsPlaying] = useState(false);
    var [playbackSpeed, setPlaybackSpeed] = useState(1);
    var playbackIntervalRef = useRef(null);
    var [dateFilter, setDateFilter] = useState('today'); // today, yesterday, custom
    var [showDatePicker, setShowDatePicker] = useState(false);
    var [isSelectingStart, setIsSelectingStart] = useState(true);
    var [startDate, setStartDate] = useState(new Date());
    var [endDate, setEndDate] = useState(new Date());

    // Yeni Premium Modüller States
    var [activeTab, setActiveTab] = useState('Bilgi'); // Bilgi, Geçmiş, Alarmlar, Komutlar
    var [showTripList, setShowTripList] = useState(false);
    var [showShareLocation, setShowShareLocation] = useState(false);

    var initialRegion = {
        latitude: 37.8746,
        longitude: 32.4931,
        latitudeDelta: 0.1,
        longitudeDelta: 0.1,
    };

    var fetchLiveVehicles = () => {
        setIsFetching(true);
        fetch('https://mehmetcelebiturizm.com/app/api/v1/vehicle-tracking/live', {
            method: 'GET',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            setIsFetching(false);
            if (data.success && data.vehicles) {
                setVehicles(data.vehicles);
            }
        })
        .catch(error => {
            setIsFetching(false);
            console.error("Araç verisi çekilemedi", error);
        });
    };

    var fetchHistoryData = () => {
        if (!selectedMarkerId) {
            alert('Lütfen önce bir araç seçin!');
            return;
        }

        var vehicle = vehicles.find(v => v.LicensePlate === selectedMarkerId);
        
        var sDate = '';
        var eDate = '';
        if (dateFilter === 'custom') {
            sDate = startDate.toISOString().split('T')[0];
            eDate = endDate.toISOString().split('T')[0];
        }

        var proceedWithId = (actualId) => {
            if (!actualId) {
                alert('Araç veritabanı IDsi bulunamadı.');
                setIsFetching(false);
                return;
            }

            var url = `https://mehmetcelebiturizm.com/app/api/v1/vehicle-tracking/history?vehicle_id=${actualId}&date_filter=${dateFilter}&start_date=${sDate}&end_date=${eDate}`;
            
            fetch(url, {
                headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
            })
            .then(res => res.json())
            .then(data => {
                setIsFetching(false);
                if (data.success && data.history && data.history.length > 0) {
                    setHistoryData(data.history);
                    setTripsData(data.trips || []);
                    setPlaybackIndex(0);
                    setIsPlaying(false);
                    setIsHistoryMode(true);
                    
                    // Reverse geocoding for start and end addresses
                    var firstPt = data.history[0];
                    var lastPt = data.history[data.history.length - 1];
                    
                    setStartAddress(parseFloat(firstPt.Latitude).toFixed(4) + ', ' + parseFloat(firstPt.Longitude).toFixed(4));
                    setEndAddress(parseFloat(lastPt.Latitude).toFixed(4) + ', ' + parseFloat(lastPt.Longitude).toFixed(4));
                    
                    fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${firstPt.Latitude}&lon=${firstPt.Longitude}`)
                        .then(r => r.json())
                        .then(res => { if(res && res.display_name) setStartAddress(res.display_name); })
                        .catch(() => {});
                        
                    fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lastPt.Latitude}&lon=${lastPt.Longitude}`)
                        .then(r => r.json())
                        .then(res => { if(res && res.display_name) setEndAddress(res.display_name); })
                        .catch(() => {});
                    
                    if (historyMapRef.current && data.history[0]) {
                        historyMapRef.current.animateToRegion({
                            latitude: parseFloat(data.history[0].Latitude),
                            longitude: parseFloat(data.history[0].Longitude),
                            latitudeDelta: 0.05,
                            longitudeDelta: 0.05
                        }, 1000);
                    }
                } else {
                    alert('Seçilen tarih aralığında veri bulunamadı.');
                }
            })
            .catch(err => {
                setIsFetching(false);
                alert('Geçmiş veri çekilirken hata oluştu.');
            });
        };

        setIsFetching(true);
        if (vehicle && vehicle.Id) {
            proceedWithId(vehicle.Id);
        } else {
            // Canlı sunucuda henüz Backend API güncellenmediği için, ID'yi /vehicles API'sinden çek
            fetch(`https://mehmetcelebiturizm.com/app/api/v1/vehicles?search=${selectedMarkerId}`, {
                headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.data && data.data.vehicles && data.data.vehicles.length > 0) {
                    var found = data.data.vehicles.find(v => v.plate === selectedMarkerId);
                    proceedWithId(found ? found.id : null);
                } else {
                    proceedWithId(null);
                }
            })
            .catch(err => {
                proceedWithId(null);
            });
        }
    };

    var togglePlayback = () => {
        setIsPlaying(!isPlaying);
    };

    var stopPlayback = () => {
        setIsPlaying(false);
        setPlaybackIndex(0);
    };

    var closeHistoryMode = () => {
        setIsHistoryMode(false);
        setIsPlaying(false);
        setHistoryData([]);
        setTripsData([]);
        setPlaybackIndex(0);
        if (playbackIntervalRef.current) clearInterval(playbackIntervalRef.current);
    };

    // Playback Engine
    useEffect(() => {
        if (isPlaying) {
            playbackIntervalRef.current = setInterval(() => {
                setPlaybackIndex(prev => {
                    if (prev >= historyData.length - 1) {
                        setIsPlaying(false);
                        return 0; // Başa sar veya dur
                    }
                    return prev + 1;
                });
            }, 1000 / playbackSpeed);
        } else {
            if (playbackIntervalRef.current) clearInterval(playbackIntervalRef.current);
        }

        return () => {
            if (playbackIntervalRef.current) clearInterval(playbackIntervalRef.current);
        };
    }, [isPlaying, playbackSpeed, historyData]);

    // Araç Playback'te İlerledikçe Harita da Odaklanabilir
    useEffect(() => {
        if (isHistoryMode && isPlaying && historyData[playbackIndex] && historyMapRef.current) {
            var curr = historyData[playbackIndex];
            if (curr) {
                historyMapRef.current.animateToRegion({
                    latitude: parseFloat(curr.Latitude),
                    longitude: parseFloat(curr.Longitude),
                    latitudeDelta: 0.01,
                    longitudeDelta: 0.01
                }, 1000 / playbackSpeed);
            }
        }
    }, [playbackIndex]);

    useEffect(() => {
        fetchLiveVehicles(); // İlk yüklemede çek
        var interval = setInterval(() => {
            fetchLiveVehicles();
        }, 10000); // 10 saniyede bir güncelle
        
        // Başlangıçta kayıtlı odaklanan aracı yükle
        AsyncStorage.getItem('focusedVehicleId').then(id => {
            if (id) {
                setFocusedVehicleId(id);
            }
        }).catch(e => console.log('Hata (focus load)', e));

        return () => clearInterval(interval);
    }, [token]);
    
    // Araçlar güncellendiğinde, odaklanılan araç varsa onu sürekli takip et
    useEffect(() => {
        if (focusedVehicleId && vehicles.length > 0 && mapRef.current) {
            var v = vehicles.find(x => x.LicensePlate === focusedVehicleId);
            if (v) {
                mapRef.current.animateToRegion({
                    latitude: parseFloat(v.Latitude),
                    longitude: parseFloat(v.Longitude),
                    latitudeDelta: 0.003,
                    longitudeDelta: 0.003
                }, 1000);
            }
        }
    }, [vehicles, focusedVehicleId]);

    // Aktif sokak görünümü aracı varsa bul
    var activeVehicle = null;
    if (activeStreetViewId) {
        activeVehicle = vehicles.find(v => v.LicensePlate === activeStreetViewId);
    }

    var goToMyLocation = async () => {
        var { status } = await Location.requestForegroundPermissionsAsync();
        if (status !== 'granted') {
            alert('Konum izni reddedildi. Lütfen ayarlardan izin verin.');
            return;
        }
        var location = await Location.getCurrentPositionAsync({});
        if (mapRef.current) {
            mapRef.current.animateToRegion({
                latitude: location.coords.latitude,
                longitude: location.coords.longitude,
                latitudeDelta: 0.002, // Maksimum derin zoom (20x)
                longitudeDelta: 0.002
            }, 1000);
        }
    };

    var fitAllVehicles = () => {
        if (!mapRef.current || vehicles.length === 0) return;
        setFocusedVehicleId(null); // Tüm araçlara sığdır dendiğinde otomatik takibi bırak
        AsyncStorage.removeItem('focusedVehicleId');
        
        var coords = vehicles.map(v => ({ latitude: parseFloat(v.Latitude), longitude: parseFloat(v.Longitude) }));
        mapRef.current.fitToCoordinates(coords, {
            edgePadding: { top: 100, right: 50, bottom: 50, left: 50 },
            animated: true
        });
    };

    // Canlı Arama (Autocomplete) Debounce Mantığı
    useEffect(() => {
        if (!searchQuery.trim()) {
            setSearchResults(null);
            setIsSearching(false);
            return;
        }

        var q = searchQuery.toLowerCase();
        
        // 1. Filonuzdaki araçları ANINDA göster (gecikmesiz)
        var matchedVehicles = vehicles.filter(v => v.LicensePlate.toLowerCase().includes(q));
        setSearchResults(prev => ({
            vehicles: matchedVehicles,
            locations: prev ? prev.locations : [] // Eskileri tut, yenisi gelene kadar
        }));
        
        setIsSearching(true);

        // 2. Harita / Adres Arama (Yazma işlemi bitince 600ms sonra başlar)
        var delayDebounceFn = setTimeout(async () => {
            var locationResults = [];
            try {
                var res = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(searchQuery)}&limit=3&countrycodes=tr`);
                var data = await res.json();
                if (data && data.length > 0) {
                    locationResults = data.map(item => ({
                        name: item.display_name,
                        lat: parseFloat(item.lat),
                        lon: parseFloat(item.lon)
                    }));
                }
            } catch (e) { console.log('Adres arama hatası', e); }

            setSearchResults({
                vehicles: matchedVehicles,
                locations: locationResults
            });
            setIsSearching(false);
        }, 700);

        return () => clearTimeout(delayDebounceFn);
    }, [searchQuery]); // Sadece arama metni değiştiğinde çalışır

    var handleSelectSearchResult = (type, item) => {
        Keyboard.dismiss(); // Klavye otomatik kapansın

        if (type === 'vehicle') {
            setSelectedMarkerId(item.LicensePlate);
            if (mapRef.current) {
                mapRef.current.animateToRegion({
                    latitude: parseFloat(item.Latitude), longitude: parseFloat(item.Longitude),
                    latitudeDelta: 0.003, longitudeDelta: 0.003
                }, 1000);
            }
        } else if (type === 'location') {
            setSelectedMarkerId(null);
            if (mapRef.current) {
                mapRef.current.animateToRegion({
                    latitude: item.lat, longitude: item.lon,
                    latitudeDelta: 0.01, longitudeDelta: 0.01
                }, 1000);
            }
        }
        setSearchResults(null);
        setSearchQuery('');
    };

    return (
        <View style={styles.fullScreen}>
            
            {/* ARAMA KUTUSU (ÜST) */}
            <View style={styles.topSearchContainer}>
                <View style={styles.searchBox}>
                    <FontAwesome5 name="search" size={16} color="#94A3B8" style={{ marginRight: 10 }} />
                    <TextInput
                        style={styles.searchInput}
                        placeholder="Plaka veya lokasyon ara..."
                        placeholderTextColor="#64748B"
                        value={searchQuery}
                        onChangeText={setSearchQuery}
                        autoCorrect={false}
                    />
                    {isSearching ? (
                        <ActivityIndicator size="small" color="#FF7300" />
                    ) : (
                        searchQuery.length > 0 ? (
                            <TouchableOpacity onPress={() => { setSearchQuery(''); setSearchResults(null); }}>
                                <FontAwesome5 name="times-circle" size={16} color="#94A3B8" />
                            </TouchableOpacity>
                        ) : null
                    )}
                </View>
                <View style={styles.userIconCircle}>
                    <FontAwesome5 name="satellite-dish" size={14} color="#10B981" />
                </View>
            </View>

            {/* ARAMA SONUÇLARI DROPDOWN */}
            {searchResults && (searchResults.vehicles.length > 0 || searchResults.locations.length > 0) && (
                <View style={styles.searchResultsContainer}>
                    <ScrollView style={{ maxHeight: 250 }} keyboardShouldPersistTaps="handled">
                        {searchResults.vehicles.length > 0 && (
                            <View>
                                <Text style={styles.searchCategoryTitle}>Filonuzdaki Araçlar</Text>
                                {searchResults.vehicles.map((v, i) => (
                                    <TouchableOpacity key={'v'+i} style={styles.searchResultItem} onPress={() => handleSelectSearchResult('vehicle', v)}>
                                        <FontAwesome5 name="car" size={14} color="#3B82F6" style={{ marginRight: 10, width: 20 }} />
                                        <Text style={styles.searchResultText}>{v.LicensePlate}</Text>
                                    </TouchableOpacity>
                                ))}
                            </View>
                        )}
                        
                        {searchResults.locations.length > 0 && (
                            <View style={{ marginTop: searchResults.vehicles.length > 0 ? 10 : 0 }}>
                                <Text style={styles.searchCategoryTitle}>Harita & Adresler</Text>
                                {searchResults.locations.map((loc, i) => (
                                    <TouchableOpacity key={'l'+i} style={styles.searchResultItem} onPress={() => handleSelectSearchResult('location', loc)}>
                                        <FontAwesome5 name="map-marker-alt" size={14} color="#F59E0B" style={{ marginRight: 10, width: 20 }} />
                                        <Text style={styles.searchResultText} numberOfLines={2}>{loc.name}</Text>
                                    </TouchableOpacity>
                                ))}
                            </View>
                        )}
                    </ScrollView>
                </View>
            )}

            {/* Native Harita Görünümü (Bölünmüş ekran mantığı) */}
            <View style={{ flex: 1, flexDirection: 'column' }}>
                
                {/* SOKAK GÖRÜNÜMÜ ÜST YARI */}
                {activeVehicle && (
                    <View style={{ flex: 1, backgroundColor: '#0F172A', position: 'relative' }}>
                        <WebView
                            ref={webViewRef}
                            source={{ html: `
                                <!DOCTYPE html>
                                <html>
                                <head>
                                    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
                                    <style>
                                        body, html { margin: 0; padding: 0; height: 100%; width: 100%; overflow: hidden; background-color: #0F172A; }
                                        iframe { width: 100%; height: 100%; border: none; }
                                    </style>
                                </head>
                                <body>
                                    <iframe src="https://maps.google.com/maps?q=&layer=c&cbll=${activeVehicle.Latitude},${activeVehicle.Longitude}&cbp=11,0,0,0,0&output=svembed" allowfullscreen></iframe>
                                </body>
                                </html>
                            ` }}
                            style={{ flex: 1 }}
                            javaScriptEnabled={true}
                            domStorageEnabled={true}
                            scrollEnabled={false}
                        />
                        {/* Sokak Görünümü Kapatma Butonu */}
                        <TouchableOpacity style={styles.closeStreetViewBtn} onPress={() => setActiveStreetViewId(null)}>
                            <FontAwesome5 name="times" size={20} color="#FFF" />
                        </TouchableOpacity>
                    </View>
                )}

                {/* HARİTA ALT (veya tam) YARI */}
                <View style={{ flex: 1, position: 'relative' }}>
                    <MapView 
                        ref={mapRef}
                        provider={PROVIDER_GOOGLE}
                        style={styles.mapView} 
                        initialRegion={initialRegion}
                        showsUserLocation={true}
                        showsMyLocationButton={false} 
                        onPress={() => setSelectedMarkerId(null)}
                    >
                {/* GEÇMİŞ MODU: Polyline ve Oynatılan Marker */}
                {isHistoryMode && historyData.length > 0 && (
                    <>
                        <Polyline 
                            coordinates={historyData.map(h => ({ latitude: parseFloat(h.lat), longitude: parseFloat(h.lng) }))}
                            strokeColor="#3B82F6"
                            strokeWidth={4}
                        />
                        <Marker 
                            coordinate={{
                                latitude: parseFloat(historyData[playbackIndex].lat),
                                longitude: parseFloat(historyData[playbackIndex].lng)
                            }}
                            anchor={{ x: 0.5, y: 0.5 }}
                        >
                            <View style={styles.historyMarkerBg}>
                                <FontAwesome5 
                                    name="car" 
                                    size={20} 
                                    color="#FFF" 
                                    style={{ transform: [{ rotate: `${historyData[playbackIndex].angle || 0}deg` }] }} 
                                />
                            </View>
                        </Marker>
                    </>
                )}

                {/* CANLI MOD: Tüm Araçlar */}
                {!isHistoryMode && vehicles.map((v, i) => {
                    var lat = parseFloat(v.Latitude);
                    var lng = parseFloat(v.Longitude);
                    if (isNaN(lat) || isNaN(lng)) return null;

                    var isEngineOn = v.EngineStatus === 'Açık' || v.Speed > 0;
                    var markerColor = isEngineOn ? '#10B981' : '#FF7300'; // Çalışanlar yeşil, duranlar turuncu

                    return (
                        <Marker 
                            key={i} 
                            coordinate={{ latitude: lat, longitude: lng }} 
                            onPress={(e) => {
                                e.stopPropagation();
                                setSelectedMarkerId(v.LicensePlate);
                            }}
                        >
                            <View style={{ alignItems: 'center', justifyContent: 'center' }}>
                                <View style={[styles.webStyleMarkerOuter, (selectedMarkerId === v.LicensePlate || activeStreetViewId === v.LicensePlate) && { borderColor: '#8B5CF6', transform: [{ scale: 1.2 }] }]}>
                                    <View style={[styles.webStyleMarkerInner, { backgroundColor: markerColor }]} />
                                </View>
                                <Text style={styles.webStyleMarkerText}>{v.LicensePlate}</Text>
                            </View>
                        </Marker>
                    );
                })}
                    </MapView>

                    {/* SEÇİLİ ARAÇ KONTROL PANELİ (BOTTOM CARD) */}
                    {selectedMarkerId && !activeStreetViewId && (
                        <View style={styles.bottomCardContainer}>
                            <View style={styles.bottomCardHeader}>
                                <Text style={styles.bottomCardTitle}>Araç: <Text style={{ color: '#FFF' }}>{selectedMarkerId}</Text></Text>
                                <TouchableOpacity onPress={() => setSelectedMarkerId(null)} style={{ padding: 5 }}>
                                    <FontAwesome5 name="times" size={14} color="#94A3B8" />
                                </TouchableOpacity>
                            </View>
                            <View style={styles.bottomCardBody}>
                                <TouchableOpacity style={styles.bottomCardBtn} onPress={() => alert('Bilgi: ' + selectedMarkerId)}>
                                    <FontAwesome5 name="info-circle" size={18} color="#3B82F6" style={{ marginBottom: 6 }} />
                                    <Text style={styles.bottomCardBtnText}>Bilgi</Text>
                                </TouchableOpacity>
                                <TouchableOpacity style={styles.bottomCardBtn} onPress={() => {
                                    // Start History Mode immediately with 'today'
                                    setDateFilter('today');
                                    fetchHistoryData();
                                }}>
                                    <FontAwesome5 name="history" size={18} color="#F59E0B" style={{ marginBottom: 6 }} />
                                    <Text style={styles.bottomCardBtnText}>Geçmiş</Text>
                                </TouchableOpacity>
                                <TouchableOpacity style={[styles.bottomCardBtn, focusedVehicleId === selectedMarkerId && { backgroundColor: 'rgba(239, 68, 68, 0.2)' }]} onPress={() => {
                                    var v = vehicles.find(x => x.LicensePlate === selectedMarkerId);
                                    if(v && mapRef.current) {
                                        if (focusedVehicleId === selectedMarkerId) {
                                            // Odak İptal Et
                                            setFocusedVehicleId(null);
                                            AsyncStorage.removeItem('focusedVehicleId');
                                            mapRef.current.animateToRegion(initialRegion, 1000);
                                        } else {
                                            // Odaklan
                                            setFocusedVehicleId(selectedMarkerId);
                                            AsyncStorage.setItem('focusedVehicleId', selectedMarkerId);
                                            mapRef.current.animateToRegion({
                                                latitude: parseFloat(v.Latitude), longitude: parseFloat(v.Longitude),
                                                latitudeDelta: 0.003, longitudeDelta: 0.003
                                            }, 1000);
                                        }
                                    }
                                }}>
                                    <FontAwesome5 name={focusedVehicleId === selectedMarkerId ? "times-circle" : "crosshairs"} size={18} color={focusedVehicleId === selectedMarkerId ? "#EF4444" : "#10B981"} style={{ marginBottom: 6 }} />
                                    <Text style={styles.bottomCardBtnText}>{focusedVehicleId === selectedMarkerId ? "Odak İptal" : "Odaklan"}</Text>
                                </TouchableOpacity>
                                <TouchableOpacity style={styles.bottomCardBtn} onPress={() => {
                                    setActiveStreetViewId(selectedMarkerId);
                                    setSelectedMarkerId(null);
                                }}>
                                    <FontAwesome5 name="street-view" size={18} color="#8B5CF6" style={{ marginBottom: 6 }} />
                                    <Text style={styles.bottomCardBtnText}>Sokak{"\n"}Görünümü</Text>
                                </TouchableOpacity>
                            </View>
                        </View>
                    )}


                    {/* Sağ Alt Kontrol Butonları */}
                    <View style={styles.mapControls}>
                        <TouchableOpacity style={styles.mapControlBtnBlue} onPress={goToMyLocation}>
                            <FontAwesome5 name="location-arrow" size={16} color="#FFF" />
                        </TouchableOpacity>
                        <TouchableOpacity style={styles.mapControlBtnWhite} onPress={fitAllVehicles}>
                            <FontAwesome5 name="expand" size={16} color="#0F172A" />
                        </TouchableOpacity>
                    </View>

                    {/* Sol Alt Hız Göstergesi (Sadece Street View açıksa) */}
                    {activeVehicle && (
                        <View style={styles.speedOverlay}>
                            <View style={styles.speedCircle}>
                                <Text style={styles.speedValue}>{activeVehicle.Speed}</Text>
                                <Text style={styles.speedUnit}>km/s</Text>
                            </View>
                        </View>
                    )}

                </View>
            </View>

            {/* YENİ GEÇMİŞ İZLEME ARAYÜZÜ */}
            <Modal visible={isHistoryMode} transparent={false} animationType="slide">
                <View style={styles.historyScreenContainer}>
                    
                    {/* Top Header */}
                    <View style={styles.historyTopHeader}>
                        <TouchableOpacity onPress={closeHistoryMode} style={styles.historyBackBtn}>
                            <FontAwesome5 name="arrow-left" size={20} color="#1E293B" />
                        </TouchableOpacity>
                        <Text style={styles.historyHeaderTitle}>Geçmiş</Text>
                        <View style={styles.historyHeaderIcons}>
                            <TouchableOpacity style={styles.historyHeaderIcon}>
                                <FontAwesome5 name="save" size={16} color="#3B82F6" />
                            </TouchableOpacity>
                            <TouchableOpacity style={styles.historyHeaderIcon}>
                                <FontAwesome5 name="th-large" size={16} color="#3B82F6" />
                            </TouchableOpacity>
                        </View>
                    </View>

                    {/* Filters Row */}
                    <View style={styles.historyFiltersRow}>
                        <TouchableOpacity style={styles.historyFilterDropdown} onPress={() => {}}>
                            <FontAwesome5 name="calendar-alt" size={14} color="#64748B" />
                            <Text style={styles.historyFilterText}>Bugün</Text>
                            <FontAwesome5 name="chevron-down" size={12} color="#64748B" style={{marginLeft: 'auto'}} />
                        </TouchableOpacity>
                        <TouchableOpacity style={styles.historyFilterDropdown} onPress={() => {}}>
                            <Text style={styles.historyFilterText} numberOfLines={1}>{selectedMarkerId}</Text>
                            <FontAwesome5 name="chevron-down" size={12} color="#64748B" style={{marginLeft: 'auto'}} />
                        </TouchableOpacity>
                    </View>

                    <View style={{ flex: 1, position: 'relative' }}>
                        {/* HISTORY MAP */}
                        <MapView 
                            provider={PROVIDER_GOOGLE}
                            style={{ flex: 1 }} 
                            initialRegion={initialRegion}
                            showsUserLocation={false}
                            showsMyLocationButton={false} 
                            ref={historyMapRef}
                        >
                            {historyData.length > 0 && (
                                <>
                                    <Polyline 
                                        coordinates={historyData.map(h => ({ latitude: parseFloat(h.Latitude), longitude: parseFloat(h.Longitude) }))}
                                        strokeColor="#3B82F6"
                                        strokeWidth={4}
                                    />
                                    
                                    {/* Start Marker */}
                                    <Marker 
                                        coordinate={{ latitude: parseFloat(historyData[0].Latitude), longitude: parseFloat(historyData[0].Longitude) }}
                                        anchor={{ x: 0.5, y: 0.5 }}
                                        tracksViewChanges={false}
                                    >
                                        <View style={[styles.historyStopMarker, { backgroundColor: '#10B981', borderColor: '#FFF' }]}>
                                            <Text style={styles.historyStopMarkerText}>A</Text>
                                        </View>
                                    </Marker>

                                    {/* End Marker */}
                                    <Marker 
                                        coordinate={{ latitude: parseFloat(historyData[historyData.length - 1].Latitude), longitude: parseFloat(historyData[historyData.length - 1].Longitude) }}
                                        anchor={{ x: 0.5, y: 0.5 }}
                                        tracksViewChanges={false}
                                    >
                                        <View style={[styles.historyStopMarker, { backgroundColor: '#EF4444', borderColor: '#FFF' }]}>
                                            <Text style={styles.historyStopMarkerText}>B</Text>
                                        </View>
                                    </Marker>
                                    
                                    {/* Stop Markers */}
                                    {tripsData.map((trip, idx) => {
                                        if (!trip.end_lat || !trip.end_lng) return null;
                                        return (
                                            <Marker 
                                                key={`stop-${idx}`}
                                                coordinate={{ latitude: parseFloat(trip.end_lat), longitude: parseFloat(trip.end_lng) }}
                                                anchor={{ x: 0.5, y: 0.5 }}
                                                tracksViewChanges={false}
                                            >
                                                <View style={styles.historyStopMarker}>
                                                    <Text style={styles.historyStopMarkerText}>P</Text>
                                                </View>
                                            </Marker>
                                        );
                                    })}

                                    {/* Playback Moving Marker */}
                                    {historyData[playbackIndex] && (
                                        <Marker 
                                            coordinate={{
                                                latitude: parseFloat(historyData[playbackIndex].Latitude),
                                                longitude: parseFloat(historyData[playbackIndex].Longitude)
                                            }}
                                            anchor={{ x: 0.5, y: 0.5 }}
                                        >
                                            <View style={styles.historyMarkerBg}>
                                                <FontAwesome5 
                                                    name="car" 
                                                    size={20} 
                                                    color="#FFF" 
                                                    style={{ transform: [{ rotate: `${historyData[playbackIndex].Course || 0}deg` }] }} 
                                                />
                                            </View>
                                        </Marker>
                                    )}
                                </>
                            )}
                        </MapView>

                        {/* Top Summary Card (Overlapping Map) */}
                        <View style={styles.historySummaryCard}>
                            <View style={styles.historyRouteLine}>
                                <View style={styles.historyRouteDotStart} />
                                <View style={styles.historyRouteLineVertical} />
                                <View style={styles.historyRouteDotEnd} />
                            </View>
                            <View style={styles.historyRouteDetails}>
                                <Text style={styles.historyRouteAddressText} numberOfLines={2}>{startAddress}</Text>
                                <Text style={styles.historyRouteDateText}>{historyData[0]?.RecordedAt || '--'}</Text>
                                
                                {(() => {
                                    var totalKm = tripsData.reduce((sum, t) => sum + (parseFloat(t.distance_km) || 0), 0);
                                    var maxSpeed = Math.max(...tripsData.map(t => parseFloat(t.max_speed) || 0), 0);
                                    var avgSpeed = tripsData.length > 0 ? (tripsData.reduce((sum, t) => sum + (parseFloat(t.avg_speed) || 0), 0) / tripsData.length) : 0;
                                    var totalSec = tripsData.reduce((sum, t) => sum + (parseInt(t.duration_seconds) || 0), 0);
                                    var hrs = Math.floor(totalSec / 3600);
                                    var mins = Math.floor((totalSec % 3600) / 60);
                                    var durationStr = hrs > 0 ? `${hrs}sa ${mins}dk` : `${mins}dk`;

                                    return (
                                        <>
                                            <View style={styles.historyRouteStatsRow}>
                                                <View style={styles.historyStatItem}>
                                                    <FontAwesome5 name="tachometer-alt" size={12} color="#3B82F6" />
                                                    <Text style={styles.historyStatText}>Ort: {avgSpeed.toFixed(0)}km/sa</Text>
                                                </View>
                                                <View style={styles.historyStatItem}>
                                                    <FontAwesome5 name="fire" size={12} color="#EF4444" />
                                                    <Text style={styles.historyStatText}>Max: {maxSpeed.toFixed(0)}km/sa</Text>
                                                </View>
                                                <View style={styles.historyStatItem}>
                                                    <FontAwesome5 name="clock" size={12} color="#10B981" />
                                                    <Text style={styles.historyStatText}>{durationStr}</Text>
                                                </View>
                                            </View>
                                            
                                            <Text style={styles.historyRouteAddressText} numberOfLines={2}>{endAddress}</Text>
                                            <Text style={styles.historyRouteDateText}>{historyData[historyData.length-1]?.RecordedAt || '--'}</Text>

                                            <View style={styles.historyTotalDistBox}>
                                                <Text style={styles.historyTotalDistText}>{totalKm.toFixed(1)}<Text style={{fontSize:10,color:'#64748B'}}>km</Text></Text>
                                            </View>
                                        </>
                                    );
                                })()}
                            </View>
                            
                            <TouchableOpacity style={styles.historyTripListBtn} onPress={() => setShowTripList(true)}>
                                <Text style={styles.historyTripListBtnText}>Sefer Listesi</Text>
                                <FontAwesome5 name="chevron-right" size={12} color="#3B82F6" />
                            </TouchableOpacity>
                        </View>

                        {/* FABs */}
                        <View style={styles.historyFabLeft}>
                            <TouchableOpacity style={styles.historyFabBtn}>
                                <FontAwesome5 name="globe" size={18} color="#475569" />
                            </TouchableOpacity>
                        </View>

                        <View style={styles.historyFabRight}>
                            <TouchableOpacity style={styles.historyFabBtn}>
                                <FontAwesome5 name="filter" size={18} color="#475569" />
                            </TouchableOpacity>
                            <TouchableOpacity style={[styles.historyFabBtn, { marginTop: 15 }]}>
                                <FontAwesome5 name="building" size={18} color="#475569" />
                            </TouchableOpacity>
                        </View>

                        {/* PLAYBACK KONTROLLERİ */}
                        {historyData.length > 0 && (
                            <View style={styles.historyPlaybackPanel}>
                                <View style={styles.playbackProgressBg}>
                                    <View style={[styles.playbackProgressFill, { width: `${(playbackIndex / (historyData.length - 1 || 1)) * 100}%` }]} />
                                </View>
                                
                                <View style={styles.playbackControlsRow}>
                                    <TouchableOpacity onPress={togglePlayback} style={styles.playPauseBtn}>
                                        <FontAwesome5 name={isPlaying ? "pause" : "play"} size={18} color="#FFF" />
                                    </TouchableOpacity>
                                    
                                    <View style={styles.playbackLiveData}>
                                        <Text style={styles.playbackLiveTime}>{historyData[playbackIndex]?.RecordedAt?.split(' ')[1] || '--'}</Text>
                                        <Text style={styles.playbackLiveSpeed}>{parseFloat(historyData[playbackIndex]?.Speed || 0).toFixed(0)} km/s</Text>
                                    </View>

                                    <View style={styles.playbackSpeedBtns}>
                                        {[1, 5, 10, 25].map(spd => (
                                            <TouchableOpacity key={spd} style={[styles.speedBtn, playbackSpeed === spd && styles.speedBtnActive]} onPress={() => setPlaybackSpeed(spd)}>
                                                <Text style={[styles.speedBtnText, playbackSpeed === spd && styles.speedBtnTextActive]}>{spd}x</Text>
                                            </TouchableOpacity>
                                        ))}
                                    </View>
                                </View>
                            </View>
                        )}

                        {/* BOTTOM TABS */}
                        <View style={styles.historyBottomTabs}>
                            <TouchableOpacity style={styles.historyBottomTabItem} onPress={() => { setActiveTab('Bilgi'); closeHistoryMode(); }}>
                                <FontAwesome5 name="info-circle" size={18} color={activeTab === 'Bilgi' ? '#3B82F6' : '#94A3B8'} />
                                <Text style={[styles.historyBottomTabText, activeTab === 'Bilgi' && { color: '#3B82F6' }]}>Bilgi</Text>
                            </TouchableOpacity>
                            <TouchableOpacity style={styles.historyBottomTabItem} onPress={() => setActiveTab('Geçmiş')}>
                                <FontAwesome5 name="history" size={20} color="#3B82F6" />
                                <Text style={[styles.historyBottomTabText, { color: '#3B82F6' }]}>Geçmiş</Text>
                            </TouchableOpacity>
                            <TouchableOpacity style={styles.historyBottomTabItem} onPress={() => setActiveTab('Alarmlar')}>
                                <FontAwesome5 name="bell" size={18} color={activeTab === 'Alarmlar' ? '#3B82F6' : '#94A3B8'} />
                                <Text style={[styles.historyBottomTabText, activeTab === 'Alarmlar' && { color: '#3B82F6' }]}>Alarmlar</Text>
                            </TouchableOpacity>
                            <TouchableOpacity style={styles.historyBottomTabItem} onPress={() => setActiveTab('Komutlar')}>
                                <FontAwesome5 name="hand-pointer" size={18} color={activeTab === 'Komutlar' ? '#3B82F6' : '#94A3B8'} />
                                <Text style={[styles.historyBottomTabText, activeTab === 'Komutlar' && { color: '#3B82F6' }]}>Komutlar</Text>
                            </TouchableOpacity>
                        </View>

                    </View>
                </View>
            </Modal>

            {/* Alt Sekmeler (Bottom Navigation Bar) */}
            <View style={styles.bottomBar}>
                <TouchableOpacity style={styles.bottomTab} onPress={() => setActiveTab('Bilgi')}>
                    <FontAwesome5 name="map-marked-alt" size={20} color={activeTab === 'Bilgi' ? "#3B82F6" : "#475569"} />
                    <Text style={[styles.bottomTabText, activeTab === 'Bilgi' && {color: '#3B82F6'}]}>Harita</Text>
                </TouchableOpacity>
                <TouchableOpacity style={styles.bottomTab} onPress={() => setActiveTab('Filo')}>
                    <FontAwesome5 name="list" size={20} color={activeTab === 'Filo' ? "#3B82F6" : "#475569"} />
                    <Text style={[styles.bottomTabText, activeTab === 'Filo' && {color: '#3B82F6'}]}>Filo</Text>
                </TouchableOpacity>
                
                {/* Share Button in middle */}
                <TouchableOpacity style={styles.bottomTabShare} onPress={() => setShowShareLocation(true)}>
                    <View style={styles.bottomTabShareInner}>
                        <FontAwesome5 name="share-alt" size={22} color="#FFF" />
                    </View>
                </TouchableOpacity>

                <TouchableOpacity style={styles.bottomTab} onPress={() => setActiveTab('Alarmlar')}>
                    <FontAwesome5 name="bell" size={20} color={activeTab === 'Alarmlar' ? "#3B82F6" : "#475569"} />
                    <Text style={[styles.bottomTabText, activeTab === 'Alarmlar' && {color: '#3B82F6'}]}>Alarmlar</Text>
                </TouchableOpacity>
                <TouchableOpacity style={styles.bottomTab} onPress={() => setActiveTab('Komutlar')}>
                    <FontAwesome5 name="terminal" size={20} color={activeTab === 'Komutlar' ? "#3B82F6" : "#475569"} />
                    <Text style={[styles.bottomTabText, activeTab === 'Komutlar' && {color: '#3B82F6'}]}>Komutlar</Text>
                </TouchableOpacity>
            </View>

            {/* PREMİUM MODÜLLER */}
            
            {/* 1. SEFER LİSTESİ MODALI */}
            <Modal visible={showTripList} transparent={true} animationType="slide">
                <View style={styles.bottomSheetOverlay}>
                    <View style={styles.bottomSheetContainer}>
                        <View style={styles.bottomSheetHeader}>
                            <Text style={styles.bottomSheetTitle}>Sefer Listesi</Text>
                            <TouchableOpacity onPress={() => setShowTripList(false)} style={{padding: 5}}>
                                <FontAwesome5 name="times" size={20} color="#64748B" />
                            </TouchableOpacity>
                        </View>
                        <ScrollView style={styles.tripListScroll}>
                            {tripsData.length === 0 && <Text style={{textAlign:'center', color:'#94A3B8', marginTop: 20}}>Bu tarihe ait sefer bulunamadı.</Text>}
                            {tripsData.map((trip, idx) => (
                                <View key={idx} style={styles.tripListItem}>
                                    <View style={styles.tripListTimeCol}>
                                        <Text style={styles.tripListTimeText}>{trip.start_time?.split(' ')[1]}</Text>
                                        <View style={styles.tripListLine} />
                                        <Text style={styles.tripListTimeText}>{trip.end_time?.split(' ')[1]}</Text>
                                    </View>
                                    <View style={styles.tripListDetailCol}>
                                        <Text style={styles.tripListLocText}>Sefer Başlangıcı</Text>
                                        <View style={styles.tripListStatsRow}>
                                            <View style={styles.tripListStatBadge}>
                                                <FontAwesome5 name="route" size={10} color="#3B82F6" />
                                                <Text style={styles.tripListStatItem}>{parseFloat(trip.distance_km).toFixed(1)} km</Text>
                                            </View>
                                            <View style={styles.tripListStatBadge}>
                                                <FontAwesome5 name="clock" size={10} color="#10B981" />
                                                <Text style={styles.tripListStatItem}>{Math.floor(trip.duration_seconds / 60)} dk</Text>
                                            </View>
                                        </View>
                                        <Text style={styles.tripListLocText}>Sefer Sonu (Park)</Text>
                                    </View>
                                </View>
                            ))}
                        </ScrollView>
                    </View>
                </View>
            </Modal>

            {/* 2. ALARMLAR MODALI */}
            <Modal visible={activeTab === 'Alarmlar'} transparent={true} animationType="slide">
                <View style={styles.bottomSheetOverlay}>
                    <View style={[styles.bottomSheetContainer, { height: '70%' }]}>
                        <View style={styles.bottomSheetHeader}>
                            <Text style={styles.bottomSheetTitle}>Son Bildirimler</Text>
                            <TouchableOpacity onPress={() => setActiveTab('Bilgi')} style={{padding: 5}}>
                                <FontAwesome5 name="times" size={20} color="#64748B" />
                            </TouchableOpacity>
                        </View>
                        <ScrollView style={styles.alarmListScroll}>
                            {[
                                { title: 'Hız İhlali', desc: 'Araç 120 km/s sınırını aştı.', time: '10 dk önce', icon: 'tachometer-alt', color: '#EF4444' },
                                { title: 'Bölge İhlali', desc: 'Araç "Merkez Depo" alanından çıktı.', time: '1 saat önce', icon: 'map-marked-alt', color: '#F59E0B' },
                                { title: 'Rölanti Alarmı', desc: 'Araç 15 dakikadan uzun süre rölantide çalıştı.', time: 'Dün', icon: 'clock', color: '#3B82F6' }
                            ].map((alarm, i) => (
                                <View key={i} style={styles.alarmItem}>
                                    <View style={[styles.alarmIconBg, { backgroundColor: alarm.color + '20' }]}>
                                        <FontAwesome5 name={alarm.icon} size={16} color={alarm.color} />
                                    </View>
                                    <View style={styles.alarmContent}>
                                        <Text style={styles.alarmTitle}>{alarm.title}</Text>
                                        <Text style={styles.alarmDesc}>{alarm.desc}</Text>
                                        <Text style={styles.alarmTime}>{alarm.time}</Text>
                                    </View>
                                </View>
                            ))}
                        </ScrollView>
                    </View>
                </View>
            </Modal>

            {/* 3. KOMUTLAR MODALI */}
            <Modal visible={activeTab === 'Komutlar'} transparent={true} animationType="slide">
                <View style={styles.bottomSheetOverlay}>
                    <View style={styles.bottomSheetContainer}>
                        <View style={styles.bottomSheetHeader}>
                            <Text style={styles.bottomSheetTitle}>Araç Komutları</Text>
                            <TouchableOpacity onPress={() => setActiveTab('Bilgi')} style={{padding: 5}}>
                                <FontAwesome5 name="times" size={20} color="#64748B" />
                            </TouchableOpacity>
                        </View>
                        <View style={styles.commandGrid}>
                            <TouchableOpacity style={styles.commandBtnCard}>
                                <View style={[styles.commandIconCircle, { backgroundColor: '#EF444420' }]}>
                                    <FontAwesome5 name="lock" size={24} color="#EF4444" />
                                </View>
                                <Text style={styles.commandBtnText}>Motoru Kilitle</Text>
                            </TouchableOpacity>
                            <TouchableOpacity style={styles.commandBtnCard}>
                                <View style={[styles.commandIconCircle, { backgroundColor: '#10B98120' }]}>
                                    <FontAwesome5 name="unlock" size={24} color="#10B981" />
                                </View>
                                <Text style={styles.commandBtnText}>Kilidi Aç</Text>
                            </TouchableOpacity>
                        </View>
                    </View>
                </View>
            </Modal>

            {/* 4. KONUM PAYLAŞ MODALI */}
            <Modal visible={showShareLocation} transparent={true} animationType="fade">
                <View style={styles.modalOverlay}>
                    <View style={styles.shareModalBox}>
                        <View style={styles.shareHeaderBg}>
                            <FontAwesome5 name="share-alt" size={24} color="#FFF" />
                        </View>
                        <Text style={styles.shareTitle}>Aracı Paylaş</Text>
                        <Text style={styles.shareDesc}>Bu aracı dışarıdan biriyle anlık takip etmesi için paylaşın.</Text>
                        
                        <View style={styles.shareDurations}>
                            <TouchableOpacity style={styles.shareDurBtn}><Text style={styles.shareDurText}>30 Dk</Text></TouchableOpacity>
                            <TouchableOpacity style={styles.shareDurBtnActive}><Text style={styles.shareDurTextActive}>1 Saat</Text></TouchableOpacity>
                            <TouchableOpacity style={styles.shareDurBtn}><Text style={styles.shareDurText}>24 Saat</Text></TouchableOpacity>
                        </View>

                        <TouchableOpacity style={styles.shareWhatsappBtn}>
                            <FontAwesome5 name="whatsapp" size={18} color="#FFF" />
                            <Text style={styles.shareWhatsappText}>WhatsApp ile Gönder</Text>
                        </TouchableOpacity>
                        <TouchableOpacity style={styles.historyCancelBtn} onPress={() => setShowShareLocation(false)}>
                            <Text style={styles.historyCancelText}>Vazgeç</Text>
                        </TouchableOpacity>
                    </View>
                </View>
            </Modal>

        </View>
    );
}

// ==============================
// 4. ANA UYGULAMA (Routing)
// ==============================
export default function App() {
    var splashState = useState(false);
    var isSplashDone = splashState[0];
    var setSplashDone = splashState[1];

    var loginState = useState(false);
    var isLoggedIn = loginState[0];
    var setIsLoggedIn = loginState[1];

    var tokenState = useState('');
    var token = tokenState[0];
    var setToken = tokenState[1];

    // Ne olursa olsun status bar'ı siyah renge zorla
    useEffect(() => {
        setStatusBarStyle('dark');
    }, []);

    // Eğer giriş yapıldıysa doğrudan haritayı göster
    if (isLoggedIn && token) {
        return (
            <View style={styles.containerRoot}>
                <MapScreen token={token} />
            </View>
        );
    }

    return (
        <View style={styles.containerRoot}>
            {/* Arka Plandaki Kusursuz 3D Neon Şehir Görseli */}
            <ImageBackground source={require('./assets/splash-bg.jpg')} style={styles.bgImage} resizeMode="cover">
                
                {/* Karartma Maskesi: Harita alt tarafta parlak kalırken, üst logonun olduğu yer kararır */}
                <View style={styles.overlay} />

                {/* Dinamik GPS Araç Efekti (Haritanın tam rota başlangıcına konumlandırıldı) */}
                <GpsCarMarker />

                {!isSplashDone ? (
                    <SplashScreen onFinish={function() { setSplashDone(true); }} />
                ) : (
                    <LoginScreen onLoginSuccess={function(t) { setToken(t); setIsLoggedIn(true); }} />
                )}

            </ImageBackground>
        </View>
    );
}

// ==============================
// 5. STİLLER
// ==============================
var styles = StyleSheet.create({
    containerRoot: { flex: 1, backgroundColor: '#020617' },
    bgImage: { flex: 1, width: '100%', height: '100%' },
    fullScreen: { flex: 1 },
    overlay: { ...StyleSheet.absoluteFillObject, backgroundColor: 'rgba(2, 6, 23, 0.4)' }, // Metinlerin okunabilirliğini artırmak için çok hafif şeffaf siyah maske
    
    // GPS Araç İkonu Stilleri (Şimdi arka plandaki GERÇEK 3D Neon Araca entegre oldu)
    carMarkerContainer: {
        position: 'absolute',
        bottom: '18%', // Yeni görseldeki muazzam 3D aracın altına konumlandırma
        left: '62%',
        alignItems: 'center',
        justifyContent: 'center',
        width: 100,
        height: 100,
        zIndex: 0,
    },
    pulseRing: {
        position: 'absolute',
        width: 30,
        height: 30,
        borderRadius: 15,
        borderWidth: 3,
        borderColor: '#FF7300', // Aracın rengine uygun parlak neon turuncu
        shadowColor: '#FF7300',
        shadowOpacity: 1,
        shadowRadius: 15,
    },

    // --- ARVENTO TARZI SADE NEON LOGO ---
    splashTop: { flex: 0.5, alignItems: 'center', justifyContent: 'center' },
    logoFilo: { fontSize: 60, fontWeight: '900', color: '#FFFFFF', letterSpacing: -2 },
    logoTakip: { fontSize: 60, fontWeight: '900', color: '#FF7300', letterSpacing: -2, textShadowColor: '#FF7300', textShadowRadius: 15 },
    logoSub: { fontSize: 18, color: '#F8FAFC', fontWeight: '500', letterSpacing: 0, marginTop: -5 },
    
    // Yükleniyor Yazısı
    splashBottomText: { flex: 0.5, justifyContent: 'flex-end', alignItems: 'center', paddingBottom: 60 },
    loadingText: { color: '#FF7300', fontSize: 11, fontWeight: '800', letterSpacing: 3, textShadowColor: 'rgba(255, 115, 0, 0.5)', textShadowRadius: 10 },

    // --- LOGİN EKRANI ---
    loginScroll: { flexGrow: 1, justifyContent: 'center', paddingHorizontal: 25 },
    loginHeader: { alignItems: 'center', marginBottom: 40 },
    logoFiloSmall: { fontSize: 44, fontWeight: '900', color: '#FFFFFF', letterSpacing: -2 },
    logoTakipSmall: { fontSize: 44, fontWeight: '900', color: '#FF7300', letterSpacing: -2, textShadowColor: '#FF7300', textShadowRadius: 10 },
    logoSubSmall: { fontSize: 14, color: '#F8FAFC', fontWeight: '500', letterSpacing: 0, marginTop: -2 },

    // Yarı Şeffaf Neon Form Kartı
    neonCard: {
        backgroundColor: 'rgba(2, 6, 23, 0.75)', // Arkadaki ışıklı şehri hafifçe gösteren şeffaf siyah
        borderRadius: 24, padding: 30,
        borderWidth: 1, borderColor: 'rgba(255, 115, 0, 0.3)', // Neon ince çerçeve
        shadowColor: '#FF7300', shadowOffset: { width: 0, height: 10 }, shadowOpacity: 0.5, shadowRadius: 20, elevation: 15,
    },
    inputGroup: { marginBottom: 25 },
    inputLabel: { fontSize: 10, fontWeight: '800', color: '#94A3B8', letterSpacing: 2, marginBottom: 10 },
    inputBox: { 
        flexDirection: 'row', alignItems: 'center', 
        backgroundColor: 'rgba(2, 6, 23, 0.6)', 
        borderRadius: 14, height: 55, paddingHorizontal: 16,
        borderWidth: 1, borderColor: '#1E293B',
    },
    inputIconBase: { marginRight: 15 },
    inputDark: { flex: 1, color: '#F8FAFC', fontSize: 16, fontWeight: '600' },
    eyeBtn: { padding: 5 },

    // Neon Buton
    neonBtn: { 
        backgroundColor: '#FF7300', 
        borderRadius: 14, height: 55, 
        alignItems: 'center', justifyContent: 'center', 
        marginTop: 10,
        shadowColor: '#FF7300', shadowOffset: { width: 0, height: 0 }, shadowOpacity: 0.6, shadowRadius: 15, elevation: 10 
    },
    neonBtnText: { color: '#020617', fontSize: 15, fontWeight: '900', letterSpacing: 2 },
    
    forgotPass: { marginTop: 25, alignItems: 'center' },
    forgotText: { color: '#FF7300', fontSize: 13, fontWeight: '600', textDecorationLine: 'underline' },

    // --- HARİTA EKRANI STİLLERİ ---
    // --- ARAMA KUTUSU (TOP SEARCH) ---
    topSearchContainer: {
        position: 'absolute', top: 50, left: 20, right: 20,
        flexDirection: 'row', alignItems: 'center',
        zIndex: 200, elevation: 10
    },
    searchBox: {
        flex: 1, flexDirection: 'row', alignItems: 'center',
        backgroundColor: '#F8FAFC', borderRadius: 12,
        height: 50, paddingHorizontal: 15,
        shadowColor: '#000', shadowOpacity: 0.1, shadowRadius: 10, elevation: 5
    },
    searchInput: {
        flex: 1, fontSize: 14, color: '#334155', fontWeight: '500'
    },
    userIconCircle: {
        width: 50, height: 50, borderRadius: 25,
        backgroundColor: '#FFFFFF', marginLeft: 10,
        alignItems: 'center', justifyContent: 'center',
        shadowColor: '#000', shadowOpacity: 0.1, shadowRadius: 10, elevation: 5
    },
    
    // --- ARAMA SONUÇLARI DROPDOWN ---
    searchResultsContainer: {
        position: 'absolute', top: 105, left: 20, right: 80, // User icon altına taşmaması için right: 80
        backgroundColor: '#FFFFFF',
        borderRadius: 12,
        paddingVertical: 10,
        shadowColor: '#000', shadowOpacity: 0.15, shadowRadius: 15, elevation: 15,
        zIndex: 201,
        maxHeight: 250
    },
    searchCategoryTitle: {
        fontSize: 10, fontWeight: 'bold', color: '#94A3B8',
        paddingHorizontal: 15, marginBottom: 5, textTransform: 'uppercase', letterSpacing: 1
    },
    searchResultItem: {
        flexDirection: 'row', alignItems: 'center',
        paddingVertical: 10, paddingHorizontal: 15,
        borderBottomWidth: 1, borderBottomColor: '#F1F5F9'
    },
    searchResultText: {
        fontSize: 13, color: '#334155', fontWeight: '500', flex: 1
    },
    
    mapView: { flex: 1, width: '100%', height: '100%' },
    
    // --- SAĞ ALT KONTROL BUTONLARI ---
    mapControls: {
        position: 'absolute',
        right: 15,
        bottom: 90, // Bottom bar'ın (70) hemen üstünde
        gap: 12,
    },
    mapControlBtnBlue: {
        width: 48, height: 48, borderRadius: 24, backgroundColor: '#3B82F6', // Arvento mavisi
        alignItems: 'center', justifyContent: 'center',
        shadowColor: '#000', shadowOpacity: 0.3, shadowRadius: 5, elevation: 5
    },
    mapControlBtnWhite: {
        width: 48, height: 48, borderRadius: 24, backgroundColor: '#FFFFFF',
        alignItems: 'center', justifyContent: 'center',
        shadowColor: '#000', shadowOpacity: 0.2, shadowRadius: 5, elevation: 5
    },

    webStyleMarkerOuter: {
        width: 26, height: 26, borderRadius: 13, backgroundColor: '#FFFFFF',
        alignItems: 'center', justifyContent: 'center',
        borderWidth: 2, borderColor: '#E2E8F0',
        shadowColor: '#000', shadowOpacity: 0.3, shadowRadius: 4, elevation: 5
    },
    webStyleMarkerInner: {
        width: 14, height: 14, borderRadius: 7
    },
    webStyleMarkerText: {
        marginTop: 5,
        fontSize: 12,
        fontWeight: '900',
        color: '#0F172A',
        backgroundColor: 'rgba(255,255,255,0.7)',
        paddingHorizontal: 4,
        borderRadius: 4,
        overflow: 'hidden'
    },
    
    // --- BOTTOM CARD MENU (Yeni Modern Panel) ---
    bottomCardContainer: {
        position: 'absolute',
        bottom: 85, // Harita kontrollerinin biraz üstü
        left: 15,
        right: 15,
        backgroundColor: 'rgba(15, 23, 42, 0.95)',
        borderRadius: 20,
        padding: 15,
        borderWidth: 1,
        borderColor: 'rgba(255, 255, 255, 0.1)',
        shadowColor: '#000', shadowOpacity: 0.5, shadowRadius: 15, elevation: 15,
        zIndex: 100
    },
    bottomCardHeader: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
        borderBottomWidth: 1,
        borderBottomColor: 'rgba(255, 255, 255, 0.1)',
        paddingBottom: 10,
        marginBottom: 10
    },
    bottomCardTitle: {
        color: '#94A3B8',
        fontSize: 14,
        fontWeight: 'bold'
    },
    bottomCardBody: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
    },
    bottomCardBtn: {
        alignItems: 'center',
        justifyContent: 'center',
        width: 70,
        height: 70,
        borderRadius: 15,
        backgroundColor: 'rgba(255, 255, 255, 0.05)', 
    },
    bottomCardBtnText: {
        color: '#F8FAFC',
        fontSize: 10,
        fontWeight: '700',
        textAlign: 'center',
    },

    // --- STREET VIEW EKRANI ÖZEL ---
    closeStreetViewBtn: {
        position: 'absolute',
        top: 130, // Google Maps'in kendi ikonlarıyla çakışmaması için biraz daha aşağı alındı
        right: 20,
        width: 44,
        height: 44,
        borderRadius: 22,
        backgroundColor: 'rgba(15, 23, 42, 0.85)',
        alignItems: 'center',
        justifyContent: 'center',
        borderWidth: 1.5,
        borderColor: 'rgba(255,255,255,0.4)',
        zIndex: 999, // WebView'in altında kalmaması için
        elevation: 999, // Android için
    },
    speedOverlay: {
        position: 'absolute',
        bottom: 90, // Bottom bar üstü
        left: 15,
        alignItems: 'center',
        justifyContent: 'center'
    },
    speedCircle: {
        width: 70,
        height: 70,
        borderRadius: 35,
        backgroundColor: 'rgba(15, 23, 42, 0.85)',
        borderWidth: 2,
        borderColor: '#10B981', // Yeşil neon hız çizgisi
        alignItems: 'center',
        justifyContent: 'center',
        shadowColor: '#10B981', shadowOpacity: 0.5, shadowRadius: 10, elevation: 10
    },
    speedValue: {
        color: '#FFF',
        fontSize: 22,
        fontWeight: '900',
    },
    speedUnit: {
        color: '#94A3B8',
        fontSize: 10,
        fontWeight: 'bold',
        marginTop: -2
    },
    // --- HISTORY PLAYBACK STYLES ---
    historyMarkerBg: {
        width: 40, height: 40,
        backgroundColor: 'rgba(59, 130, 246, 0.2)',
        borderRadius: 20, alignItems: 'center', justifyContent: 'center'
    },
    historyMarkerIcon: {
        width: 30, height: 30
    },
    playbackContainer: {
        position: 'absolute', bottom: 85, left: 10, right: 10,
        backgroundColor: 'rgba(15, 23, 42, 0.95)',
        borderRadius: 20, padding: 15,
        borderWidth: 1, borderColor: 'rgba(59, 130, 246, 0.3)'
    },
    playbackHeader: {
        flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center',
        marginBottom: 10
    },
    playbackTimeText: { color: '#E2E8F0', fontSize: 13, fontWeight: '700' },
    playbackSpeedText: { color: '#10B981', fontSize: 14, fontWeight: '900' },
    playbackCloseBtn: { padding: 5 },
    playbackSpeedBtns: {
        flexDirection: 'row', justifyContent: 'space-between', marginBottom: 15
    },
    speedBtn: {
        flex: 1, backgroundColor: 'rgba(255,255,255,0.05)',
        marginHorizontal: 3, paddingVertical: 6,
        borderRadius: 8, alignItems: 'center'
    },
    speedBtnActive: { backgroundColor: '#3B82F6' },
    speedBtnText: { color: '#94A3B8', fontSize: 12, fontWeight: 'bold' },
    speedBtnTextActive: { color: '#FFF' },
    playbackControls: {
        flexDirection: 'row', alignItems: 'center'
    },
    playPauseBtn: {
        width: 44, height: 44, borderRadius: 22, backgroundColor: '#3B82F6',
        alignItems: 'center', justifyContent: 'center', marginRight: 15
    },
    playbackProgressBg: {
        flex: 1, height: 8, backgroundColor: 'rgba(255,255,255,0.1)', borderRadius: 4
    },
    playbackProgressFill: {
        height: '100%', backgroundColor: '#3B82F6', borderRadius: 4
    },
    
    // --- HISTORY MODAL (TARİH SEÇİMİ) ---
    modalOverlay: {
        flex: 1, backgroundColor: 'rgba(0,0,0,0.7)',
        justifyContent: 'center', alignItems: 'center', padding: 20
    },
    historyModalBox: {
        width: '100%', backgroundColor: '#0F172A',
        borderRadius: 20, padding: 20,
        borderWidth: 1, borderColor: '#1E293B'
    },
    historyModalTitle: {
        color: '#FFF', fontSize: 18, fontWeight: '900',
        marginBottom: 20, textAlign: 'center'
    },
    dateFilterTabs: {
        flexDirection: 'row', marginBottom: 20,
        backgroundColor: 'rgba(255,255,255,0.05)', borderRadius: 10, padding: 4
    },
    dateTab: {
        flex: 1, paddingVertical: 10, alignItems: 'center', borderRadius: 8
    },
    dateTabActive: { backgroundColor: '#3B82F6' },
    dateTabText: { color: '#94A3B8', fontSize: 13, fontWeight: 'bold' },
    dateTabTextActive: { color: '#FFF' },
    customDateArea: {
        flexDirection: 'row', justifyContent: 'space-between', marginBottom: 20
    },
    customDateBtn: {
        flex: 1, backgroundColor: 'rgba(255,255,255,0.05)',
        marginHorizontal: 5, padding: 12, borderRadius: 10
    },
    customDateLabel: { color: '#94A3B8', fontSize: 10, marginBottom: 5 },
    customDateValue: { color: '#FFF', fontSize: 13, fontWeight: 'bold' },
    historyModalFooter: {
        flexDirection: 'row', justifyContent: 'flex-end', marginTop: 10
    },
    historyCancelBtn: {
        paddingVertical: 12, paddingHorizontal: 20, marginRight: 10
    },
    historyCancelText: { color: '#94A3B8', fontSize: 14, fontWeight: 'bold' },
    historyFetchBtn: {
        backgroundColor: '#3B82F6', paddingVertical: 12, paddingHorizontal: 25, borderRadius: 12
    },
    historyFetchText: { color: '#FFF', fontSize: 14, fontWeight: 'bold' },

    // --- YENİ GEÇMİŞ ARAYÜZÜ (PREMIUM BEYAZ/AÇIK TEMA) ---
    historyScreenContainer: { flex: 1, backgroundColor: '#F8FAFC' },
    historyTopHeader: {
        flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between',
        paddingHorizontal: 20, paddingTop: Platform.OS === 'ios' ? 50 : 20, paddingBottom: 15,
        backgroundColor: '#FFF'
    },
    historyBackBtn: { padding: 5 },
    historyHeaderTitle: { fontSize: 18, fontWeight: '900', color: '#1E293B', marginLeft: -20 },
    historyHeaderIcons: { flexDirection: 'row', gap: 15 },
    historyHeaderIcon: {
        width: 36, height: 36, borderRadius: 18, backgroundColor: 'rgba(59, 130, 246, 0.1)',
        alignItems: 'center', justifyContent: 'center'
    },
    historyFiltersRow: {
        flexDirection: 'row', gap: 10, paddingHorizontal: 20, paddingBottom: 15, backgroundColor: '#FFF',
        borderBottomWidth: 1, borderBottomColor: '#E2E8F0', zIndex: 10
    },
    historyFilterDropdown: {
        flex: 1, flexDirection: 'row', alignItems: 'center', gap: 8,
        backgroundColor: '#FFF', borderWidth: 1, borderColor: '#CBD5E1', borderRadius: 20, paddingHorizontal: 15, paddingVertical: 10
    },
    historyFilterText: { fontSize: 13, fontWeight: '700', color: '#334155' },
    
    historySummaryCard: {
        position: 'absolute', top: 15, left: 15, right: 15,
        backgroundColor: 'rgba(255, 255, 255, 0.95)', borderRadius: 24, padding: 20,
        shadowColor: '#000', shadowOffset: { width: 0, height: 10 }, shadowOpacity: 0.1, shadowRadius: 20, elevation: 10
    },
    historyStopMarker: {
        width: 24, height: 24, borderRadius: 12, backgroundColor: '#EF4444', 
        borderWidth: 2, borderColor: '#FFF', alignItems: 'center', justifyContent: 'center'
    },
    historyStopMarkerText: {
        color: '#FFF', fontSize: 10, fontWeight: 'bold'
    },
    historyRouteLine: { position: 'absolute', left: 24, top: 25, bottom: 65, alignItems: 'center' },
    historyRouteDotStart: { width: 14, height: 14, borderRadius: 7, borderColor: '#3B82F6', borderWidth: 3, backgroundColor: '#FFF' },
    historyRouteLineVertical: { width: 3, flex: 1, backgroundColor: '#3B82F6', marginVertical: 4 },
    historyRouteDotEnd: { width: 14, height: 14, borderRadius: 7, borderColor: '#3B82F6', borderWidth: 3, backgroundColor: '#FFF' },
    historyRouteDetails: { marginLeft: 25 },
    historyRouteAddressText: { fontSize: 13, color: '#475569', fontWeight: '500', marginBottom: 2 },
    historyRouteDateText: { fontSize: 11, color: '#94A3B8', marginBottom: 15 },
    historyRouteStatsRow: {
        flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 12, borderTopWidth: 1, borderBottomWidth: 1, borderColor: '#E2E8F0', marginBottom: 15
    },
    historyStatItem: { flexDirection: 'row', alignItems: 'center', gap: 5 },
    historyStatText: { fontSize: 12, fontWeight: '700', color: '#64748B' },
    historyTotalDistBox: { position: 'absolute', left: 5, top: '40%', backgroundColor: '#FFF', paddingHorizontal: 4 },
    historyTotalDistText: { fontSize: 16, fontWeight: '900', color: '#0F172A' },
    historyTripListBtn: { position: 'absolute', right: 20, bottom: 25, flexDirection: 'row', alignItems: 'center', gap: 5 },
    historyTripListBtnText: { fontSize: 13, fontWeight: '800', color: '#3B82F6' },

    historyPlaybackPanel: {
        position: 'absolute', bottom: 100, left: 15, right: 15,
        backgroundColor: '#1E293B', borderRadius: 20, padding: 15,
        shadowColor: '#000', shadowOffset: { width: 0, height: 10 }, shadowOpacity: 0.3, shadowRadius: 15, elevation: 10
    },
    playbackProgressBg: {
        height: 6, backgroundColor: '#334155', borderRadius: 3, overflow: 'hidden', marginBottom: 15
    },
    playbackProgressFill: {
        height: '100%', backgroundColor: '#3B82F6'
    },
    playbackControlsRow: {
        flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between'
    },
    playPauseBtn: {
        width: 44, height: 44, borderRadius: 22, backgroundColor: '#3B82F6',
        alignItems: 'center', justifyContent: 'center'
    },
    playbackLiveData: {
        alignItems: 'center', marginHorizontal: 10, flex: 1
    },
    playbackLiveTime: { color: '#94A3B8', fontSize: 12, fontWeight: '700', marginBottom: 2 },
    playbackLiveSpeed: { color: '#FFF', fontSize: 16, fontWeight: '900' },
    playbackSpeedBtns: {
        flexDirection: 'row', gap: 5
    },
    speedBtn: {
        paddingVertical: 6, paddingHorizontal: 10, borderRadius: 10, backgroundColor: '#334155'
    },
    speedBtnActive: {
        backgroundColor: '#3B82F6'
    },
    speedBtnText: {
        color: '#94A3B8', fontSize: 12, fontWeight: 'bold'
    },
    speedBtnTextActive: {
        color: '#FFF'
    },

    historyFabLeft: { position: 'absolute', left: 20, bottom: 200 },
    historyFabRight: { position: 'absolute', right: 20, bottom: 200 },
    historyFabBtn: {
        width: 50, height: 50, borderRadius: 25, backgroundColor: '#FFF',
        alignItems: 'center', justifyContent: 'center',
        shadowColor: '#000', shadowOffset: { width: 0, height: 5 }, shadowOpacity: 0.15, shadowRadius: 10, elevation: 5
    },

    historyBottomTabs: {
        position: 'absolute', bottom: 20, left: 20, right: 20,
        backgroundColor: '#FFF', borderRadius: 30, height: 65,
        flexDirection: 'row', alignItems: 'center', justifyContent: 'space-around',
        shadowColor: '#000', shadowOffset: { width: 0, height: 10 }, shadowOpacity: 0.1, shadowRadius: 20, elevation: 10
    },
    historyBottomTabItem: { alignItems: 'center', justifyContent: 'center' },
    historyBottomTabText: { fontSize: 10, fontWeight: '700', color: '#94A3B8', marginTop: 4 },

    // --- ALT SEKMELER (BOTTOM BAR) ---
    bottomBar: {
        position: 'absolute', bottom: 0, left: 0, right: 0,
        height: 70, backgroundColor: '#FFF',
        flexDirection: 'row', justifyContent: 'space-around', alignItems: 'center',
        borderTopWidth: 1, borderTopColor: '#E2E8F0',
        paddingBottom: Platform.OS === 'ios' ? 15 : 0
    },
    bottomTab: { alignItems: 'center', justifyContent: 'center', flex: 1 },
    bottomTabText: { fontSize: 10, fontWeight: '700', color: '#475569', marginTop: 4 },
    bottomTabShare: { alignItems: 'center', justifyContent: 'center', flex: 1, marginTop: -20 },
    bottomTabShareInner: { width: 56, height: 56, borderRadius: 28, backgroundColor: '#10B981', alignItems: 'center', justifyContent: 'center', shadowColor: '#10B981', shadowOffset: {width: 0, height: 8}, shadowOpacity: 0.4, shadowRadius: 15, elevation: 8 },

    // --- PREMIUM MODAL & BOTTOM SHEET STYLES ---
    bottomSheetOverlay: { flex: 1, justifyContent: 'flex-end', backgroundColor: 'rgba(15, 23, 42, 0.4)' },
    bottomSheetContainer: { backgroundColor: '#FFF', borderTopLeftRadius: 30, borderTopRightRadius: 30, padding: 20, paddingBottom: 40, shadowColor: '#000', shadowOffset: {width: 0, height: -10}, shadowOpacity: 0.1, shadowRadius: 20, elevation: 20, maxHeight: '80%' },
    bottomSheetHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 20 },
    bottomSheetTitle: { fontSize: 18, fontWeight: '900', color: '#1E293B' },
    
    // Trip List
    tripListScroll: { flexGrow: 0 },
    tripListItem: { flexDirection: 'row', marginBottom: 20 },
    tripListTimeCol: { width: 60, alignItems: 'center' },
    tripListTimeText: { fontSize: 11, fontWeight: 'bold', color: '#64748B' },
    tripListLine: { width: 2, flex: 1, backgroundColor: '#E2E8F0', marginVertical: 5 },
    tripListDetailCol: { flex: 1, backgroundColor: '#F8FAFC', borderRadius: 15, padding: 15, marginLeft: 10, borderWidth: 1, borderColor: '#F1F5F9' },
    tripListLocText: { fontSize: 13, color: '#334155', fontWeight: '600' },
    tripListStatsRow: { flexDirection: 'row', gap: 10, marginVertical: 8 },
    tripListStatBadge: { flexDirection: 'row', alignItems: 'center', gap: 4, backgroundColor: '#FFF', paddingHorizontal: 8, paddingVertical: 4, borderRadius: 8, borderWidth: 1, borderColor: '#E2E8F0' },
    tripListStatItem: { fontSize: 11, fontWeight: 'bold', color: '#475569' },

    // Alarms
    alarmListScroll: { flexGrow: 0 },
    alarmItem: { flexDirection: 'row', alignItems: 'center', marginBottom: 15, backgroundColor: '#F8FAFC', padding: 15, borderRadius: 16, borderWidth: 1, borderColor: '#F1F5F9' },
    alarmIconBg: { width: 44, height: 44, borderRadius: 22, alignItems: 'center', justifyContent: 'center' },
    alarmContent: { flex: 1, marginLeft: 15 },
    alarmTitle: { fontSize: 14, fontWeight: '800', color: '#1E293B', marginBottom: 2 },
    alarmDesc: { fontSize: 12, color: '#64748B' },
    alarmTime: { fontSize: 10, color: '#94A3B8', marginTop: 4, fontWeight: '600' },

    // Commands
    commandGrid: { flexDirection: 'row', gap: 15, justifyContent: 'center', marginTop: 10 },
    commandBtnCard: { flex: 1, backgroundColor: '#F8FAFC', borderRadius: 20, padding: 25, alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: '#F1F5F9' },
    commandIconCircle: { width: 60, height: 60, borderRadius: 30, alignItems: 'center', justifyContent: 'center', marginBottom: 15 },
    commandBtnText: { fontSize: 14, fontWeight: '800', color: '#1E293B' },

    // Share Location
    shareModalBox: { width: '85%', backgroundColor: '#FFF', borderRadius: 30, padding: 25, alignItems: 'center', shadowColor: '#000', shadowOffset: {width: 0, height: 20}, shadowOpacity: 0.15, shadowRadius: 30, elevation: 20 },
    shareHeaderBg: { width: 60, height: 60, borderRadius: 30, backgroundColor: '#10B981', alignItems: 'center', justifyContent: 'center', marginTop: -50, marginBottom: 15, shadowColor: '#10B981', shadowOffset: {width: 0, height: 10}, shadowOpacity: 0.3, shadowRadius: 15, elevation: 10 },
    shareTitle: { fontSize: 20, fontWeight: '900', color: '#1E293B', marginBottom: 10 },
    shareDesc: { fontSize: 13, color: '#64748B', textAlign: 'center', marginBottom: 25, paddingHorizontal: 10 },
    shareDurations: { flexDirection: 'row', gap: 10, marginBottom: 25 },
    shareDurBtn: { flex: 1, paddingVertical: 12, borderRadius: 12, backgroundColor: '#F1F5F9', alignItems: 'center' },
    shareDurText: { fontSize: 12, fontWeight: 'bold', color: '#64748B' },
    shareDurBtnActive: { flex: 1, paddingVertical: 12, borderRadius: 12, backgroundColor: '#10B981', alignItems: 'center' },
    shareDurTextActive: { fontSize: 12, fontWeight: 'bold', color: '#FFF' },
    shareWhatsappBtn: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 10, backgroundColor: '#25D366', width: '100%', paddingVertical: 15, borderRadius: 15, marginBottom: 15 },
    shareWhatsappText: { fontSize: 15, fontWeight: 'bold', color: '#FFF' }
});
