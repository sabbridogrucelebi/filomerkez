import React, { useState, useEffect, useContext, useRef } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, ActivityIndicator, Dimensions, Animated, ScrollView, Platform, Alert, Image } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import MapView, { Marker, PROVIDER_GOOGLE } from '../components/MapProxy';
import { MaterialCommunityIcons as Icon, Ionicons } from '@expo/vector-icons';
import { AuthContext } from '../context/AuthContext';
import axios from '../api/axios';
import { LinearGradient } from 'expo-linear-gradient';
import { BlurView } from 'expo-blur';

const { width, height } = Dimensions.get('window');

// Dark/Premium Map Style
const premiumMapStyle = [
  { "elementType": "geometry", "stylers": [{ "color": "#212121" }] },
  { "elementType": "labels.icon", "stylers": [{ "visibility": "off" }] },
  { "elementType": "labels.text.fill", "stylers": [{ "color": "#757575" }] },
  { "elementType": "labels.text.stroke", "stylers": [{ "color": "#212121" }] },
  { "featureType": "administrative", "elementType": "geometry", "stylers": [{ "color": "#757575" }] },
  { "featureType": "administrative.country", "elementType": "labels.text.fill", "stylers": [{ "color": "#9e9e9e" }] },
  { "featureType": "administrative.land_parcel", "stylers": [{ "visibility": "off" }] },
  { "featureType": "administrative.locality", "elementType": "labels.text.fill", "stylers": [{ "color": "#bdbdbd" }] },
  { "featureType": "poi", "elementType": "labels.text.fill", "stylers": [{ "color": "#757575" }] },
  { "featureType": "poi.park", "elementType": "geometry", "stylers": [{ "color": "#181818" }] },
  { "featureType": "poi.park", "elementType": "labels.text.fill", "stylers": [{ "color": "#616161" }] },
  { "featureType": "poi.park", "elementType": "labels.text.stroke", "stylers": [{ "color": "#1b1b1b" }] },
  { "featureType": "road", "elementType": "geometry.fill", "stylers": [{ "color": "#2c2c2c" }] },
  { "featureType": "road", "elementType": "labels.text.fill", "stylers": [{ "color": "#8a8a8a" }] },
  { "featureType": "road.arterial", "elementType": "geometry", "stylers": [{ "color": "#373737" }] },
  { "featureType": "road.highway", "elementType": "geometry", "stylers": [{ "color": "#3c3c3c" }] },
  { "featureType": "road.highway.controlled_access", "elementType": "geometry", "stylers": [{ "color": "#4e4e4e" }] },
  { "featureType": "road.local", "elementType": "labels.text.fill", "stylers": [{ "color": "#616161" }] },
  { "featureType": "transit", "elementType": "labels.text.fill", "stylers": [{ "color": "#757575" }] },
  { "featureType": "water", "elementType": "geometry", "stylers": [{ "color": "#000000" }] },
  { "featureType": "water", "elementType": "labels.text.fill", "stylers": [{ "color": "#3d3d3d" }] }
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

    // Animations
    const sheetAnim = useRef(new Animated.Value(0)).current;
    const [sheetOpen, setSheetOpen] = useState(true);

    useEffect(() => {
        fetchLiveLocations();
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
        } finally {
            if (showLoading) setLoading(false);
        }
    };

    const toggleSheet = () => {
        const toValue = sheetOpen ? 1 : 0;
        Animated.spring(sheetAnim, {
            toValue,
            useNativeDriver: true,
            friction: 8,
            tension: 50
        }).start();
        setSheetOpen(!sheetOpen);
    };

    const focusOnVehicle = (vehicle) => {
        setSelectedVehicle(vehicle);
        if (mapRef.current && vehicle.Latitude && vehicle.Longitude) {
            mapRef.current.animateToRegion({
                latitude: parseFloat(vehicle.Latitude),
                longitude: parseFloat(vehicle.Longitude),
                latitudeDelta: 0.005,
                longitudeDelta: 0.005,
            }, 1000);
        }
    };

    const activeCount = vehicles.filter(v => (v.EngineStatus === 'Açık' || parseInt(v.Speed || 0) > 0)).length;

    if (loading) {
        return (
            <View style={s.center}>
                <ActivityIndicator size="large" color="#6366F1" />
                <Text style={{ color: '#9CA3AF', marginTop: 12, fontWeight: '600' }}>Uydudan veri alınıyor...</Text>
            </View>
        );
    }

    if (!providerActive) {
        return (
            <SafeAreaView style={s.center}>
                <Icon name="satellite-variant" size={64} color="#4B5563" />
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
            {/* Harita */}
            <MapView
                ref={mapRef}
                style={[StyleSheet.absoluteFillObject]}
                provider={PROVIDER_GOOGLE}
                customMapStyle={premiumMapStyle}
                showsUserLocation={false}
                showsMyLocationButton={false}
                showsCompass={false}
                toolbarEnabled={false}
            >
                {vehicles.map((v, index) => {
                    const lat = parseFloat(v.Latitude);
                    const lng = parseFloat(v.Longitude);
                    const speed = parseInt(v.Speed || 0);
                    
                    const now = Math.floor(Date.now() / 1000);
                    const dataTime = v.Timestamp || 0;
                    const diffMins = dataTime > 0 ? Math.floor((now - dataTime) / 60) : 999999;
                    
                    let markerColors = ['#F87171', '#DC2626']; // Red
                    let borderColor = '#EF4444';
                    let iconName = 'car-off';
                    let isMoving = false;

                    if (diffMins >= 48 * 60) {
                        markerColors = ['#334155', '#1e293b']; // Black (Slate-800)
                        borderColor = '#1e293b';
                        iconName = 'cloud-off-outline';
                    } else if (diffMins >= 15) {
                        markerColors = ['#94a3b8', '#64748b']; // Gray
                        borderColor = '#94a3b8';
                        iconName = 'signal-off';
                    } else if (speed > 0) {
                        markerColors = ['#22d3ee', '#0891b2']; // Cyan
                        borderColor = '#06b6d4';
                        iconName = 'navigation';
                        isMoving = true;
                    } else if (v.ACC) {
                        markerColors = ['#fb923c', '#ea580c']; // Orange
                        borderColor = '#f97316';
                        iconName = 'engine';
                    }

                    const isSelected = selectedVehicle && selectedVehicle.LicensePlate === v.LicensePlate;

                    return (
                        <Marker
                            key={index}
                            coordinate={{ latitude: lat, longitude: lng }}
                            onPress={() => focusOnVehicle(v)}
                            tracksViewChanges={false}
                            zIndex={isSelected ? 999 : 1}
                        >
                            <View style={s.markerWrap}>
                                <View style={[
                                    s.pulsingCircle, 
                                    { borderColor: borderColor },
                                    isSelected && { width: 50, height: 50, borderRadius: 25, borderWidth: 2 }
                                ]}>
                                    <LinearGradient
                                        colors={markerColors}
                                        style={s.markerCore}
                                    >
                                        <Icon name={iconName} size={14} color="#FFF" style={isMoving ? { transform: [{ rotate: '45deg' }] } : {}} />
                                    </LinearGradient>
                                </View>
                                {isSelected && (
                                    <View style={s.markerLabel}>
                                        <Text style={s.markerPlate}>{v.LicensePlate}</Text>
                                    </View>
                                )}
                            </View>
                        </Marker>
                    );
                })}
            </MapView>

            {/* Glassmorphism Header */}
            <View style={s.topContainer}>
                <BlurView intensity={70} tint="dark" style={s.headerBlur}>
                    <SafeAreaView edges={['top']} style={{ paddingBottom: 15 }}>
                        <View style={s.headerRow}>
                            <TouchableOpacity style={s.glassBtn} onPress={() => navigation.goBack()}>
                                <Icon name="arrow-left" size={22} color="#FFF" />
                            </TouchableOpacity>
                            
                            <View style={s.headerTitleWrap}>
                                <Text style={s.headerTitle}>Canlı Filo Takibi</Text>
                                <View style={s.headerSubWrap}>
                                    <View style={[s.liveIndicator, { backgroundColor: '#10B981' }]} />
                                    <Text style={s.headerSub}>{providerName} Üzerinden Bağlı</Text>
                                </View>
                            </View>

                            <TouchableOpacity style={s.glassBtn} onPress={() => fetchLiveLocations(true)}>
                                <Icon name="refresh" size={22} color="#FFF" />
                            </TouchableOpacity>
                        </View>

                        {/* Top Stats */}
                        <View style={s.statsContainer}>
                            <View style={s.statBox}>
                                <Text style={s.statValText}>{vehicles.length}</Text>
                                <Text style={s.statLblText}>Toplam</Text>
                            </View>
                            <View style={s.statBoxDivider} />
                            <View style={s.statBox}>
                                <Text style={[s.statValText, { color: '#34D399' }]}>{activeCount}</Text>
                                <Text style={s.statLblText}>Aktif Araç</Text>
                            </View>
                            <View style={s.statBoxDivider} />
                            <View style={s.statBox}>
                                <Text style={s.statValText}>{userInfo?.name || 'Admin'}</Text>
                                <Text style={s.statLblText}>Kullanıcı</Text>
                            </View>
                        </View>
                    </SafeAreaView>
                </BlurView>
            </View>

            {/* Floating Action Buttons */}
            <View style={s.fabContainer}>
                <TouchableOpacity style={s.fabBtn} onPress={() => {
                    if (mapRef.current && vehicles.length > 0) {
                        const coords = vehicles.map(v => ({ latitude: parseFloat(v.Latitude), longitude: parseFloat(v.Longitude) }));
                        mapRef.current.fitToCoordinates(coords, { edgePadding: { top: 150, right: 50, bottom: height * 0.45, left: 50 }, animated: true });
                    }
                }}>
                    <Icon name="fit-to-screen-outline" size={22} color="#FFF" />
                </TouchableOpacity>
            </View>

            {/* Glassmorphism Bottom Sheet */}
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
                <BlurView intensity={90} tint="dark" style={StyleSheet.absoluteFillObject} />
                
                <View style={s.sheetHandleWrap}>
                    <TouchableOpacity style={s.sheetHandleClickArea} onPress={toggleSheet}>
                        <View style={s.sheetHandle} />
                    </TouchableOpacity>
                </View>

                <View style={s.sheetHeader}>
                    <Text style={s.sheetTitle}>Filo Araçları</Text>
                    <Text style={s.sheetSub}>Son Güncelleme: <Text style={{color: '#FFF'}}>{lastUpdated}</Text></Text>
                </View>

                <ScrollView style={s.sheetScroll} showsVerticalScrollIndicator={false}>
                    {vehicles.length === 0 ? (
                        <Text style={s.noDataText}>Gösterilecek araç bulunamadı.</Text>
                    ) : (
                        vehicles.map((v, i) => {
                            const speed = parseInt(v.Speed || 0);
                            
                            const now = Math.floor(Date.now() / 1000);
                            const dataTime = v.Timestamp || 0;
                            const diffMins = dataTime > 0 ? Math.floor((now - dataTime) / 60) : 999999;
                            
                            let listColors = ['rgba(239, 68, 68, 0.2)', 'rgba(220, 38, 38, 0.1)']; // Red
                            let listIconColor = '#F87171';
                            let listIconName = 'car';
                            const isOn = speed > 0; // For speed text color

                            if (diffMins >= 48 * 60) {
                                listColors = ['rgba(30, 41, 59, 0.5)', 'rgba(15, 23, 42, 0.4)']; // Black
                                listIconColor = '#475569';
                                listIconName = 'cloud-off-outline';
                            } else if (diffMins >= 15) {
                                listColors = ['rgba(148, 163, 184, 0.3)', 'rgba(100, 116, 139, 0.2)']; // Gray
                                listIconColor = '#94a3b8';
                                listIconName = 'signal-off';
                            } else if (speed > 0) {
                                listColors = ['rgba(6, 182, 212, 0.3)', 'rgba(8, 145, 178, 0.2)']; // Cyan
                                listIconColor = '#22d3ee';
                                listIconName = 'car-connected';
                            } else if (v.ACC) {
                                listColors = ['rgba(249, 115, 22, 0.3)', 'rgba(234, 88, 12, 0.2)']; // Orange
                                listIconColor = '#fb923c';
                                listIconName = 'engine';
                            }

                            const isSelected = selectedVehicle && selectedVehicle.LicensePlate === v.LicensePlate;

                            return (
                                <TouchableOpacity 
                                    key={i} 
                                    style={[s.vehicleCard, isSelected && s.vehicleCardSelected]}
                                    onPress={() => focusOnVehicle(v)}
                                    activeOpacity={0.7}
                                >
                                    <LinearGradient
                                        colors={listColors}
                                        style={s.vIconWrap}
                                    >
                                        <Icon name={listIconName} size={22} color={listIconColor} />
                                    </LinearGradient>
                                    <View style={s.vInfo}>
                                        <Text style={s.vPlate}>{v.LicensePlate}</Text>
                                        <Text style={s.vDriver} numberOfLines={1}>
                                            <Icon name="account" size={12} color="#9CA3AF" /> {v.Driver || 'Bilinmiyor'}
                                        </Text>
                                    </View>
                                    <View style={s.vSpeedWrap}>
                                        <Text style={[s.vSpeed, {color: isOn ? '#FFF' : '#D1D5DB'}]}>
                                            {speed} <Text style={{fontSize: 10, color: '#9CA3AF'}}>km/h</Text>
                                        </Text>
                                        <View style={s.vStatusWrap}>
                                            <View style={[s.statusDotSmall, {backgroundColor: isOn ? '#10B981' : '#EF4444'}]} />
                                            <Text style={[s.vStatus, {color: isOn ? '#34D399' : '#F87171'}]}>{isOn ? 'HAREKETLİ' : 'DURAN'}</Text>
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
    container: { flex: 1, backgroundColor: '#111827' },
    center: { flex: 1, backgroundColor: '#111827', alignItems: 'center', justifyContent: 'center', padding: 24 },
    errorTitle: { fontSize: 22, fontWeight: '800', color: '#F3F4F6', marginTop: 16, marginBottom: 8 },
    errorDesc: { fontSize: 14, color: '#9CA3AF', textAlign: 'center', marginBottom: 24 },
    backBtn: { paddingHorizontal: 24, paddingVertical: 12, backgroundColor: '#6366F1', borderRadius: 12 },
    backBtnText: { color: '#FFF', fontWeight: '700', fontSize: 14 },

    // Markers
    markerWrap: { alignItems: 'center', justifyContent: 'center' },
    pulsingCircle: { width: 36, height: 36, borderRadius: 18, borderWidth: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: 'rgba(17, 24, 39, 0.4)' },
    markerCore: { width: 24, height: 24, borderRadius: 12, alignItems: 'center', justifyContent: 'center', shadowColor: '#000', shadowOffset: { width: 0, height: 4 }, shadowOpacity: 0.5, shadowRadius: 5 },
    markerLabel: { marginTop: 4, backgroundColor: 'rgba(17, 24, 39, 0.8)', paddingHorizontal: 8, paddingVertical: 4, borderRadius: 6, borderWidth: 1, borderColor: '#374151' },
    markerPlate: { color: '#F9FAFB', fontSize: 10, fontWeight: '800' },

    // Header Blur
    topContainer: { position: 'absolute', top: 0, left: 0, right: 0, zIndex: 10 },
    headerBlur: { borderBottomWidth: 1, borderBottomColor: 'rgba(255, 255, 255, 0.05)' },
    headerRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 20, paddingTop: 10, paddingBottom: 10 },
    glassBtn: { width: 40, height: 40, borderRadius: 12, backgroundColor: 'rgba(255, 255, 255, 0.1)', alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: 'rgba(255, 255, 255, 0.1)' },
    
    headerTitleWrap: { flex: 1, alignItems: 'center' },
    headerTitle: { fontSize: 18, fontWeight: '800', color: '#FFF', letterSpacing: 0.5 },
    headerSubWrap: { flexDirection: 'row', alignItems: 'center', marginTop: 4 },
    liveIndicator: { width: 8, height: 8, borderRadius: 4, marginRight: 6, shadowColor: '#10B981', shadowOffset: { width: 0, height: 0 }, shadowOpacity: 0.8, shadowRadius: 4 },
    headerSub: { fontSize: 11, fontWeight: '600', color: '#9CA3AF' },

    statsContainer: { flexDirection: 'row', justifyContent: 'space-around', alignItems: 'center', marginTop: 10, paddingHorizontal: 10 },
    statBox: { alignItems: 'center', flex: 1 },
    statBoxDivider: { width: 1, height: 24, backgroundColor: 'rgba(255, 255, 255, 0.1)' },
    statValText: { fontSize: 16, fontWeight: '800', color: '#FFF' },
    statLblText: { fontSize: 10, color: '#9CA3AF', marginTop: 2, fontWeight: '600', letterSpacing: 0.5 },

    // FAB
    fabContainer: { position: 'absolute', right: 16, bottom: height * 0.45 + 16, zIndex: 9 },
    fabBtn: { width: 48, height: 48, borderRadius: 24, backgroundColor: 'rgba(17, 24, 39, 0.8)', alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: 'rgba(255, 255, 255, 0.1)', shadowColor: '#000', shadowOffset: { width: 0, height: 4 }, shadowOpacity: 0.3, shadowRadius: 4 },

    // Bottom Sheet (Glassmorphism)
    bottomSheet: {
        position: 'absolute',
        bottom: 0, left: 0, right: 0,
        height: height * 0.45,
        borderTopLeftRadius: 32,
        borderTopRightRadius: 32,
        overflow: 'hidden',
        borderWidth: 1,
        borderColor: 'rgba(255, 255, 255, 0.05)',
        borderBottomWidth: 0
    },
    sheetHandleWrap: { alignItems: 'center', width: '100%', paddingTop: 10 },
    sheetHandleClickArea: { width: 100, height: 30, alignItems: 'center', justifyContent: 'center' },
    sheetHandle: { width: 40, height: 4, borderRadius: 2, backgroundColor: 'rgba(255, 255, 255, 0.3)' },
    
    sheetHeader: { paddingHorizontal: 24, paddingBottom: 16, borderBottomWidth: 1, borderBottomColor: 'rgba(255, 255, 255, 0.05)' },
    sheetTitle: { fontSize: 18, fontWeight: '800', color: '#FFF' },
    sheetSub: { fontSize: 11, color: '#9CA3AF', marginTop: 4, fontWeight: '500' },

    sheetScroll: { flex: 1 },
    noDataText: { textAlign: 'center', color: '#6B7280', marginTop: 24, fontSize: 13 },
    
    vehicleCard: {
        flexDirection: 'row',
        alignItems: 'center',
        paddingVertical: 16,
        paddingHorizontal: 24,
        borderBottomWidth: 1,
        borderBottomColor: 'rgba(255, 255, 255, 0.03)',
    },
    vehicleCardSelected: { backgroundColor: 'rgba(255, 255, 255, 0.05)' },
    
    vIconWrap: { width: 44, height: 44, borderRadius: 14, alignItems: 'center', justifyContent: 'center', marginRight: 16, borderWidth: 1, borderColor: 'rgba(255, 255, 255, 0.1)' },
    vInfo: { flex: 1 },
    vPlate: { fontSize: 15, fontWeight: '700', color: '#FFF', marginBottom: 4 },
    vDriver: { fontSize: 12, color: '#9CA3AF', fontWeight: '500' },

    vSpeedWrap: { alignItems: 'flex-end' },
    vSpeed: { fontSize: 18, fontWeight: '800' },
    vStatusWrap: { flexDirection: 'row', alignItems: 'center', marginTop: 4 },
    statusDotSmall: { width: 6, height: 6, borderRadius: 3, marginRight: 4, shadowColor: '#10B981', shadowOffset: { width: 0, height: 0 }, shadowOpacity: 0.5, shadowRadius: 2 },
    vStatus: { fontSize: 9, fontWeight: '700', letterSpacing: 0.5 }
});
