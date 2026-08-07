import React, { useState, useEffect, useContext, useRef } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, ActivityIndicator, Dimensions, Animated, ScrollView, Platform, Alert, Image } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import MapView, { Marker, PROVIDER_GOOGLE } from '../components/MapProxy';
import { MaterialCommunityIcons as Icon, Ionicons } from '@expo/vector-icons';
import { AuthContext } from '../context/AuthContext';
import axios from '../api/axios';
import { LinearGradient } from 'expo-linear-gradient';

const { width, height } = Dimensions.get('window');

// Light Map Style (matching web)
const mapStyle = [
  { "featureType": "administrative", "elementType": "geometry", "stylers": [{ "visibility": "off" }] },
  { "featureType": "poi", "stylers": [{ "visibility": "off" }] },
  { "featureType": "road", "elementType": "labels.icon", "stylers": [{ "visibility": "off" }] },
  { "featureType": "transit", "stylers": [{ "visibility": "off" }] }
];

export default function TrackingScreen({ navigation }) {
    const { userInfo } = useContext(AuthContext);
    const mapRef = useRef(null);

    const [loading, setLoading] = useState(true);
    const [vehicles, setVehicles] = useState([]);
    const [providerActive, setProviderActive] = useState(true);
    const [providerName, setProviderName] = useState('Bilinmiyor');
    const [selectedVehicle, setSelectedVehicle] = useState(null);
    const [lastUpdated, setLastUpdated] = useState(null);

    // Animasyonlar
    const sheetAnim = useRef(new Animated.Value(0)).current;
    const [sheetOpen, setSheetOpen] = useState(true);

    useEffect(() => {
        fetchLiveLocations();
        
        // 15 saniyede bir otomatik güncelleme
        const interval = setInterval(() => {
            fetchLiveLocations(false);
        }, 15000);

        return () => clearInterval(interval);
    }, []);

    const fetchLiveLocations = async (showLoading = true) => {
        if (showLoading) setLoading(true);
        try {
            const res = await axios.get('/v1/vehicle-tracking/live');
            
            if (res.data && res.data.vehicles) {
                const dataObj = res.data.vehicles;
                const vehiclesArray = Object.values(dataObj).filter(v => v.Latitude != null && v.Longitude != null);
                
                setVehicles(vehiclesArray);
                setProviderActive(res.data.provider_active !== false);
                setProviderName(res.data.provider_name || 'Arvento');
                setLastUpdated(new Date().toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit', second: '2-digit' }));
                
                // Haritayı ilk yüklemede araçlara odakla
                if (showLoading && vehiclesArray.length > 0 && mapRef.current) {
                    const coords = vehiclesArray.map(v => ({ latitude: parseFloat(v.Latitude), longitude: parseFloat(v.Longitude) }));
                    setTimeout(() => {
                        mapRef.current.fitToCoordinates(coords, {
                            edgePadding: { top: 100, right: 50, bottom: height * 0.45, left: 50 },
                            animated: true,
                        });
                    }, 1000);
                }
            }
        } catch (error) {
            console.error("Araç takip verisi çekilemedi:", error);
            alert("Veri çekilemedi: " + (error.response?.data?.message || error.message));
        } finally {
            if (showLoading) setLoading(false);
        }
    };

    const toggleSheet = () => {
        const toValue = sheetOpen ? 1 : 0;
        Animated.spring(sheetAnim, {
            toValue,
            useNativeDriver: true,
            friction: 8
        }).start();
        setSheetOpen(!sheetOpen);
    };

    const focusOnVehicle = (vehicle) => {
        setSelectedVehicle(vehicle);
        if (mapRef.current && vehicle.Latitude && vehicle.Longitude) {
            mapRef.current.animateToRegion({
                latitude: parseFloat(vehicle.Latitude),
                longitude: parseFloat(vehicle.Longitude),
                latitudeDelta: 0.01,
                longitudeDelta: 0.01,
            }, 1000);
        }
    };

    const activeCount = vehicles.filter(v => (v.EngineStatus === 'Açık' || parseInt(v.Speed || 0) > 0)).length;

    if (loading) {
        return (
            <View style={s.center}>
                <ActivityIndicator size="large" color="#3B82F6" />
                <Text style={{ color: '#64748B', marginTop: 12 }}>Uydudan veri alınıyor...</Text>
            </View>
        );
    }

    if (!providerActive) {
        return (
            <SafeAreaView style={s.center}>
                <Icon name="satellite-variant" size={64} color="#94A3B8" />
                <Text style={s.errorTitle}>Takip Sistemi Kapalı</Text>
                <Text style={s.errorDesc}>Araç takip sistemi entegrasyonunuz aktif değil. Lütfen web panelden ayarlarınızı yapın.</Text>
                <TouchableOpacity style={s.backBtn} onPress={() => navigation.goBack()}>
                    <Text style={s.backBtnText}>Geri Dön</Text>
                </TouchableOpacity>
            </SafeAreaView>
        );
    }

    return (
        <View style={s.container}>
            {/* Arka Plan Gradient (Light) */}
            <LinearGradient colors={['#F8FAFC', '#F1F5F9']} style={StyleSheet.absoluteFillObject} />

            {/* Harita */}
            <MapView
                ref={mapRef}
                style={[StyleSheet.absoluteFillObject, { bottom: height * 0.4 }]}
                provider={PROVIDER_GOOGLE}
                customMapStyle={mapStyle}
                showsUserLocation={false}
                showsMyLocationButton={false}
                showsCompass={false}
                toolbarEnabled={false}
            >
                {vehicles.map((v, index) => {
                    const lat = parseFloat(v.Latitude);
                    const lng = parseFloat(v.Longitude);
                    const speed = parseInt(v.Speed || 0);
                    const isOn = v.EngineStatus === 'Açık' || speed > 0;
                    const isSelected = selectedVehicle && selectedVehicle.LicensePlate === v.LicensePlate;

                    return (
                        <Marker
                            key={index}
                            coordinate={{ latitude: lat, longitude: lng }}
                            onPress={() => focusOnVehicle(v)}
                            tracksViewChanges={false}
                        >
                            <View style={[s.dotMarkerOuter, isOn ? s.dotOuterOn : s.dotOuterOff, isSelected && s.dotOuterSelected]}>
                                <View style={[s.dotMarkerInner, isOn ? s.dotInnerOn : s.dotInnerOff]} />
                            </View>
                        </Marker>
                    );
                })}
            </MapView>

            {/* Üst Kısım: Başlık ve Bilgi Kartları */}
            <SafeAreaView style={s.topContainer} edges={['top']}>
                {/* Başlık Barı */}
                <View style={s.headerRow}>
                    <TouchableOpacity style={s.iconBtn} onPress={() => navigation.goBack()}>
                        <Icon name="arrow-left" size={24} color="#1E293B" />
                    </TouchableOpacity>
                    
                    <View style={s.headerTitleWrap}>
                        <View style={s.titleGroup}>
                            <View style={s.titleLine} />
                            <View>
                                <Text style={s.headerTitle}>Araç Takip</Text>
                                <View style={s.headerSubWrap}>
                                    <View style={s.liveDotSmall} />
                                    <Text style={s.headerSub}>ENTEGRASYON AYARLARI VE CANLI TAKİP</Text>
                                </View>
                            </View>
                        </View>
                    </View>

                    <TouchableOpacity style={s.iconBtn} onPress={() => fetchLiveLocations(true)}>
                        <Icon name="refresh" size={24} color="#1E293B" />
                    </TouchableOpacity>
                </View>

                {/* İstatistik Kartları (Yatay Scroll) */}
                <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={s.statsScroll}>
                    <View style={s.statCard}>
                        <View style={[s.statIconWrap, { backgroundColor: '#EFF6FF' }]}>
                            <Icon name="satellite-variant" size={20} color="#3B82F6" />
                        </View>
                        <View>
                            <Text style={s.statLabel}>SİSTEM</Text>
                            <Text style={s.statValue}>{providerName}</Text>
                        </View>
                    </View>
                    
                    <View style={s.statCard}>
                        <View style={[s.statIconWrap, { backgroundColor: '#ECFDF5' }]}>
                            <View style={s.liveDotLarge} />
                        </View>
                        <View>
                            <Text style={s.statLabel}>AKTİF ARAÇ</Text>
                            <Text style={s.statValue}>{activeCount}</Text>
                        </View>
                    </View>

                    <View style={s.statCard}>
                        <View style={[s.statIconWrap, { backgroundColor: '#F3F4F6' }]}>
                            <Icon name="account" size={20} color="#4B5563" />
                        </View>
                        <View>
                            <Text style={s.statLabel}>KULLANICI</Text>
                            <Text style={s.statValue} numberOfLines={1}>{userInfo?.name || 'Kullanıcı'}</Text>
                        </View>
                    </View>
                </ScrollView>

                {/* Butonlar */}
                <View style={s.actionRow}>
                    <TouchableOpacity style={s.primaryBtn} onPress={() => navigation.navigate('TrackingReports')}>
                        <Icon name="file-document-outline" size={16} color="#FFF" style={{marginRight:6}} />
                        <Text style={s.primaryBtnText}>RAPORLAR</Text>
                    </TouchableOpacity>
                    
                    <TouchableOpacity style={s.secondaryBtn} onPress={() => alert('Web panele yönlendiriliyor...')}>
                        <Text style={s.secondaryBtnText}>AYARLARI DEĞİŞTİR</Text>
                    </TouchableOpacity>
                </View>
            </SafeAreaView>

            {/* Bottom Sheet (Araç Listesi) */}
            <Animated.View style={[
                s.bottomSheet, 
                { 
                    transform: [{ 
                        translateY: sheetAnim.interpolate({
                            inputRange: [0, 1],
                            outputRange: [0, height * 0.4]
                        }) 
                    }] 
                }
            ]}>
                <View style={s.sheetHandleWrap}>
                    <TouchableOpacity style={s.sheetHandleClickArea} onPress={toggleSheet}>
                        <View style={s.sheetHandle} />
                    </TouchableOpacity>
                </View>

                <View style={s.sheetHeader}>
                    <Text style={s.sheetTitle}>Araç Listesi</Text>
                    <Text style={s.sheetSub}>Son Güncelleme: {lastUpdated}</Text>
                </View>

                <ScrollView style={s.sheetScroll} showsVerticalScrollIndicator={false}>
                    {vehicles.length === 0 ? (
                        <Text style={s.noDataText}>Gösterilecek araç bulunamadı.</Text>
                    ) : (
                        vehicles.map((v, i) => {
                            const speed = parseInt(v.Speed || 0);
                            const isOn = v.EngineStatus === 'Açık' || speed > 0;
                            const isSelected = selectedVehicle && selectedVehicle.LicensePlate === v.LicensePlate;

                            return (
                                <TouchableOpacity 
                                    key={i} 
                                    style={[s.vehicleCard, isSelected && s.vehicleCardSelected]}
                                    onPress={() => focusOnVehicle(v)}
                                    activeOpacity={0.7}
                                >
                                    <View style={[s.vIconWrap, { backgroundColor: isOn ? '#ECFDF5' : '#FEF2F2' }]}>
                                        <Text style={{fontSize: 20}}>🚚</Text>
                                    </View>
                                    <View style={s.vInfo}>
                                        <Text style={s.vPlate}>{v.LicensePlate}</Text>
                                        <Text style={s.vDriver} numberOfLines={1}>{v.Driver || 'Bilinmiyor'}</Text>
                                    </View>
                                    <View style={s.vSpeedWrap}>
                                        <Text style={[s.vSpeed, {color: '#3B82F6'}]}>{speed} <Text style={{fontSize: 10}}>km/h</Text></Text>
                                        <View style={s.vStatusWrap}>
                                            <View style={[s.statusDotSmall, {backgroundColor: isOn ? '#10B981' : '#EF4444'}]} />
                                            <Text style={[s.vStatus, {color: isOn ? '#10B981' : '#EF4444'}]}>{isOn ? 'HAREKETLİ' : 'DURAN'}</Text>
                                        </View>
                                    </View>
                                </TouchableOpacity>
                            );
                        })
                    )}
                    <View style={{height: 100}}/>
                </ScrollView>
            </Animated.View>
        </View>
    );
}

const s = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#FFFFFF' },
    center: { flex: 1, backgroundColor: '#FFFFFF', alignItems: 'center', justifyContent: 'center', padding: 24 },
    errorTitle: { fontSize: 20, fontWeight: '800', color: '#1E293B', marginTop: 16, marginBottom: 8 },
    errorDesc: { fontSize: 14, color: '#64748B', textAlign: 'center', marginBottom: 24 },
    backBtn: { paddingHorizontal: 24, paddingVertical: 12, backgroundColor: '#3B82F6', borderRadius: 12 },
    backBtnText: { color: '#FFF', fontWeight: '700', fontSize: 14 },

    // Harita Nokta İkonları
    dotMarkerOuter: { width: 24, height: 24, borderRadius: 12, alignItems: 'center', justifyContent: 'center', borderWidth: 2 },
    dotOuterOn: { backgroundColor: 'rgba(16, 185, 129, 0.2)', borderColor: 'rgba(16, 185, 129, 0.4)' },
    dotOuterOff: { backgroundColor: 'rgba(239, 68, 68, 0.2)', borderColor: 'rgba(239, 68, 68, 0.4)' },
    dotOuterSelected: { width: 36, height: 36, borderRadius: 18, borderWidth: 3, borderColor: '#3B82F6' },
    dotMarkerInner: { width: 12, height: 12, borderRadius: 6, shadowColor: '#000', shadowOffset: { width: 0, height: 2 }, shadowOpacity: 0.3, shadowRadius: 3 },
    dotInnerOn: { backgroundColor: '#10B981' },
    dotInnerOff: { backgroundColor: '#EF4444' },

    // Üst Kısım
    topContainer: { position: 'absolute', top: 0, left: 0, right: 0, zIndex: 10, paddingBottom: 10 },
    headerRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 16, paddingTop: 10, paddingBottom: 16 },
    iconBtn: { width: 44, height: 44, borderRadius: 22, backgroundColor: '#FFFFFF', alignItems: 'center', justifyContent: 'center', shadowColor: '#000', shadowOffset: { width: 0, height: 2 }, shadowOpacity: 0.1, shadowRadius: 4, elevation: 2 },
    
    headerTitleWrap: { flex: 1, paddingHorizontal: 12 },
    titleGroup: { flexDirection: 'row', alignItems: 'center' },
    titleLine: { width: 4, height: 36, backgroundColor: '#8B5CF6', borderRadius: 2, marginRight: 10 },
    headerTitle: { fontSize: 22, fontWeight: '900', color: '#1E293B', letterSpacing: -0.5 },
    headerSubWrap: { flexDirection: 'row', alignItems: 'center', marginTop: 2 },
    liveDotSmall: { width: 6, height: 6, borderRadius: 3, backgroundColor: '#10B981', marginRight: 6 },
    headerSub: { fontSize: 9, fontWeight: '800', color: '#94A3B8', letterSpacing: 0.5 },

    statsScroll: { paddingHorizontal: 16, gap: 12, paddingBottom: 16 },
    statCard: { flexDirection: 'row', alignItems: 'center', backgroundColor: '#FFFFFF', padding: 12, borderRadius: 16, minWidth: 140, shadowColor: '#000', shadowOffset: { width: 0, height: 4 }, shadowOpacity: 0.05, shadowRadius: 8, elevation: 2 },
    statIconWrap: { width: 40, height: 40, borderRadius: 12, alignItems: 'center', justifyContent: 'center', marginRight: 12 },
    liveDotLarge: { width: 14, height: 14, borderRadius: 7, backgroundColor: '#10B981', borderWidth: 3, borderColor: '#A7F3D0' },
    statLabel: { fontSize: 9, fontWeight: '800', color: '#94A3B8', letterSpacing: 1, marginBottom: 2 },
    statValue: { fontSize: 15, fontWeight: '900', color: '#1E293B' },

    actionRow: { flexDirection: 'row', paddingHorizontal: 16, gap: 12, justifyContent: 'flex-end' },
    primaryBtn: { flexDirection: 'row', alignItems: 'center', backgroundColor: '#4F46E5', paddingHorizontal: 16, paddingVertical: 10, borderRadius: 20 },
    primaryBtnText: { color: '#FFF', fontSize: 11, fontWeight: '800', letterSpacing: 0.5 },
    secondaryBtn: { alignItems: 'center', justifyContent: 'center', backgroundColor: '#0F172A', paddingHorizontal: 16, paddingVertical: 10, borderRadius: 20 },
    secondaryBtnText: { color: '#FFF', fontSize: 11, fontWeight: '800', letterSpacing: 0.5 },

    // Bottom Sheet (Araç Listesi)
    bottomSheet: {
        position: 'absolute',
        bottom: 0, left: 0, right: 0,
        height: height * 0.45,
        backgroundColor: '#FFFFFF',
        borderTopLeftRadius: 32,
        borderTopRightRadius: 32,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: -10 },
        shadowOpacity: 0.1,
        shadowRadius: 20,
        elevation: 20,
    },
    sheetHandleWrap: { alignItems: 'center', width: '100%' },
    sheetHandleClickArea: { width: 100, height: 30, alignItems: 'center', justifyContent: 'center' },
    sheetHandle: { width: 40, height: 5, borderRadius: 3, backgroundColor: '#E2E8F0' },
    
    sheetHeader: { paddingHorizontal: 24, paddingBottom: 16, borderBottomWidth: 1, borderBottomColor: '#F1F5F9' },
    sheetTitle: { fontSize: 18, fontWeight: '900', color: '#1E293B' },
    sheetSub: { fontSize: 11, color: '#94A3B8', marginTop: 4, fontWeight: '600' },

    sheetScroll: { flex: 1 },
    noDataText: { textAlign: 'center', color: '#94A3B8', marginTop: 24, fontSize: 13 },
    
    vehicleCard: {
        flexDirection: 'row',
        alignItems: 'center',
        paddingVertical: 16,
        paddingHorizontal: 24,
        borderBottomWidth: 1,
        borderBottomColor: '#F8FAFC',
    },
    vehicleCardSelected: { backgroundColor: '#F0FDF4' },
    
    vIconWrap: { width: 40, height: 40, borderRadius: 12, alignItems: 'center', justifyContent: 'center', marginRight: 16 },
    vInfo: { flex: 1 },
    vPlate: { fontSize: 15, fontWeight: '800', color: '#1E293B', marginBottom: 4 },
    vDriver: { fontSize: 11, color: '#64748B', fontWeight: '500' },

    vSpeedWrap: { alignItems: 'flex-end' },
    vSpeed: { fontSize: 16, fontWeight: '900' },
    vStatusWrap: { flexDirection: 'row', alignItems: 'center', marginTop: 4 },
    statusDotSmall: { width: 6, height: 6, borderRadius: 3, marginRight: 4 },
    vStatus: { fontSize: 9, fontWeight: '800', letterSpacing: 0.5 }
});
