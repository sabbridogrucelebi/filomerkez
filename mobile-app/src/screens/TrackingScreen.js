import React, { useState, useEffect, useContext, useRef } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, ActivityIndicator, Dimensions, Animated, ScrollView, Platform, Alert } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import MapView, { Marker, PROVIDER_GOOGLE } from '../components/MapProxy';
import { MaterialCommunityIcons as Icon } from '@expo/vector-icons';
import { AuthContext } from '../context/AuthContext';
import axios from '../api/axios';
import { LinearGradient } from 'expo-linear-gradient';

const { width, height } = Dimensions.get('window');

// Premium Dark Map Style
const mapStyle = [
  { "elementType": "geometry", "stylers": [{ "color": "#242f3e" }] },
  { "elementType": "labels.text.fill", "stylers": [{ "color": "#746855" }] },
  { "elementType": "labels.text.stroke", "stylers": [{ "color": "#242f3e" }] },
  { "featureType": "administrative.locality", "elementType": "labels.text.fill", "stylers": [{ "color": "#d59563" }] },
  { "featureType": "poi", "elementType": "labels.text.fill", "stylers": [{ "color": "#d59563" }] },
  { "featureType": "poi.park", "elementType": "geometry", "stylers": [{ "color": "#263c3f" }] },
  { "featureType": "poi.park", "elementType": "labels.text.fill", "stylers": [{ "color": "#6b9a76" }] },
  { "featureType": "road", "elementType": "geometry", "stylers": [{ "color": "#38414e" }] },
  { "featureType": "road", "elementType": "geometry.stroke", "stylers": [{ "color": "#212a37" }] },
  { "featureType": "road", "elementType": "labels.text.fill", "stylers": [{ "color": "#9ca5b3" }] },
  { "featureType": "road.highway", "elementType": "geometry", "stylers": [{ "color": "#746855" }] },
  { "featureType": "road.highway", "elementType": "geometry.stroke", "stylers": [{ "color": "#1f2835" }] },
  { "featureType": "road.highway", "elementType": "labels.text.fill", "stylers": [{ "color": "#f3d19c" }] },
  { "featureType": "transit", "elementType": "geometry", "stylers": [{ "color": "#2f3948" }] },
  { "featureType": "transit.station", "elementType": "labels.text.fill", "stylers": [{ "color": "#d59563" }] },
  { "featureType": "water", "elementType": "geometry", "stylers": [{ "color": "#17263c" }] },
  { "featureType": "water", "elementType": "labels.text.fill", "stylers": [{ "color": "#515c6d" }] },
  { "featureType": "water", "elementType": "labels.text.stroke", "stylers": [{ "color": "#17263c" }] }
];

export default function TrackingScreen({ navigation }) {
    const { userToken } = useContext(AuthContext);
    const mapRef = useRef(null);

    const [loading, setLoading] = useState(true);
    const [vehicles, setVehicles] = useState([]);
    const [providerActive, setProviderActive] = useState(true);
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
                // Objeden diziye çevir (Arvento formatında obje gelebilir)
                const dataObj = res.data.vehicles;
                const vehiclesArray = Object.values(dataObj).filter(v => v.Latitude != null && v.Longitude != null);
                
                setVehicles(vehiclesArray);
                setProviderActive(res.data.provider_active !== false); // Default true if undefined
                setLastUpdated(new Date().toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit', second: '2-digit' }));
                
                if (vehiclesArray.length === 0) {
                     alert("API'den yanıt geldi ancak araç dizisi boş! (Arvento'dan veri gelmiyor olabilir)");
                }

                // Haritayı ilk yüklemede araçlara odakla
                if (showLoading && vehiclesArray.length > 0 && mapRef.current) {
                    const coords = vehiclesArray.map(v => ({ latitude: parseFloat(v.Latitude), longitude: parseFloat(v.Longitude) }));
                    setTimeout(() => {
                        mapRef.current.fitToCoordinates(coords, {
                            edgePadding: { top: 100, right: 50, bottom: 250, left: 50 },
                            animated: true,
                        });
                    }, 1000);
                }
            } else {
                alert("API yanıt verdi ancak içinde 'vehicles' verisi yok. Yanıt: " + JSON.stringify(res.data));
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

    if (loading) {
        return (
            <View style={s.center}>
                <ActivityIndicator size="large" color="#3B82F6" />
                <Text style={{ color: '#94A3B8', marginTop: 12 }}>Uydudan veri alınıyor...</Text>
            </View>
        );
    }

    if (!providerActive) {
        return (
            <SafeAreaView style={s.center}>
                <Icon name="satellite-variant" size={64} color="#334155" />
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
            <MapView
                ref={mapRef}
                style={StyleSheet.absoluteFillObject}
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

            {/* Header / Back Button */}
            <SafeAreaView style={s.headerWrap} edges={['top']}>
                <View style={s.headerInner}>
                    <TouchableOpacity style={s.headerBtn} onPress={() => navigation.goBack()}>
                        <Icon name="arrow-left" size={24} color="#FFF" />
                    </TouchableOpacity>
                    <View style={{flex:1, alignItems:'center'}}>
                        <Text style={s.headerTitle}>Canlı Araç Takip</Text>
                        <View style={s.liveBadgeWrap}>
                            <View style={s.liveDot} />
                            <Text style={s.liveText}>Canlı İzleme ({vehicles.length} Araç)</Text>
                        </View>
                    </View>
                    <TouchableOpacity style={s.headerBtn} onPress={() => fetchLiveLocations(true)}>
                        <Icon name="refresh" size={24} color="#FFF" />
                    </TouchableOpacity>
                </View>
            </SafeAreaView>

            {/* Bottom Sheet */}
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
                    <Text style={s.sheetTitle}>Filo Durumu</Text>
                    <Text style={s.sheetSub}>Son Güncelleme: {lastUpdated}</Text>
                </View>

                <ScrollView style={s.sheetScroll} showsVerticalScrollIndicator={false}>
                    {vehicles.length === 0 ? (
                        <Text style={s.noDataText}>Haritada gösterilecek araç bulunamadı.</Text>
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
                                    <View style={[s.statusIndicator, isOn ? s.statusOn : s.statusOff]} />
                                    <View style={s.vInfo}>
                                        <Text style={s.vPlate}>{v.LicensePlate}</Text>
                                        <Text style={s.vDriver} numberOfLines={1}>{v.Driver || 'Bilinmiyor'}</Text>
                                    </View>
                                    <View style={s.vSpeedWrap}>
                                        <Text style={[s.vSpeed, isOn ? {color: '#10B981'} : {color: '#64748B'}]}>{speed} km/s</Text>
                                        <Text style={s.vStatus}>{isOn ? 'Hareket Halinde' : 'Park Halinde'}</Text>
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
    container: { flex: 1, backgroundColor: '#0F172A' },
    center: { flex: 1, backgroundColor: '#0F172A', alignItems: 'center', justifyContent: 'center', padding: 24 },
    errorTitle: { fontSize: 20, fontWeight: '800', color: '#F8FAFC', marginTop: 16, marginBottom: 8 },
    errorDesc: { fontSize: 14, color: '#94A3B8', textAlign: 'center', marginBottom: 24 },
    backBtn: { paddingHorizontal: 24, paddingVertical: 12, backgroundColor: '#3B82F6', borderRadius: 12 },
    backBtnText: { color: '#FFF', fontWeight: '700', fontSize: 14 },

    // Harita Nokta İkonları (Markers)
    dotMarkerOuter: {
        width: 24,
        height: 24,
        borderRadius: 12,
        alignItems: 'center',
        justifyContent: 'center',
        borderWidth: 2,
    },
    dotOuterOn: { backgroundColor: 'rgba(16, 185, 129, 0.2)', borderColor: 'rgba(16, 185, 129, 0.4)' },
    dotOuterOff: { backgroundColor: 'rgba(239, 68, 68, 0.2)', borderColor: 'rgba(239, 68, 68, 0.4)' },
    dotOuterSelected: { width: 36, height: 36, borderRadius: 18, borderWidth: 3, borderColor: '#FFF' },
    
    dotMarkerInner: {
        width: 12,
        height: 12,
        borderRadius: 6,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.3,
        shadowRadius: 3,
    },
    dotInnerOn: { backgroundColor: '#10B981' },
    dotInnerOff: { backgroundColor: '#EF4444' },

    // Header
    headerWrap: { position: 'absolute', top: 0, left: 0, right: 0, zIndex: 10 },
    headerInner: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 16, paddingTop: 10, paddingBottom: 16 },
    headerBtn: { width: 44, height: 44, borderRadius: 22, backgroundColor: 'rgba(15, 23, 42, 0.6)', alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)' },
    headerTitle: { fontSize: 16, fontWeight: '800', color: '#FFF', marginBottom: 4 },
    liveBadgeWrap: { flexDirection: 'row', alignItems: 'center', backgroundColor: 'rgba(16, 185, 129, 0.15)', paddingHorizontal: 10, paddingVertical: 4, borderRadius: 12, borderWidth: 1, borderColor: 'rgba(16, 185, 129, 0.3)' },
    liveDot: { width: 6, height: 6, borderRadius: 3, backgroundColor: '#10B981', marginRight: 6 },
    liveText: { fontSize: 10, fontWeight: '700', color: '#10B981' },

    // Bottom Sheet
    bottomSheet: {
        position: 'absolute',
        bottom: 0, left: 0, right: 0,
        height: height * 0.5,
        backgroundColor: '#0F172A',
        borderTopLeftRadius: 32,
        borderTopRightRadius: 32,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: -10 },
        shadowOpacity: 0.3,
        shadowRadius: 20,
        elevation: 20,
        borderWidth: 1,
        borderColor: 'rgba(255,255,255,0.05)',
    },
    sheetHandleWrap: { alignItems: 'center', width: '100%' },
    sheetHandleClickArea: { width: 100, height: 30, alignItems: 'center', justifyContent: 'center' },
    sheetHandle: { width: 40, height: 5, borderRadius: 3, backgroundColor: '#334155' },
    
    sheetHeader: { paddingHorizontal: 24, paddingBottom: 16, borderBottomWidth: 1, borderBottomColor: 'rgba(255,255,255,0.05)' },
    sheetTitle: { fontSize: 18, fontWeight: '800', color: '#F8FAFC' },
    sheetSub: { fontSize: 11, color: '#64748B', marginTop: 4 },

    sheetScroll: { flex: 1, paddingTop: 8 },
    noDataText: { textAlign: 'center', color: '#64748B', marginTop: 24, fontSize: 13 },
    
    vehicleCard: {
        flexDirection: 'row',
        alignItems: 'center',
        paddingVertical: 14,
        paddingHorizontal: 24,
        borderBottomWidth: 1,
        borderBottomColor: 'rgba(255,255,255,0.03)',
    },
    vehicleCardSelected: { backgroundColor: 'rgba(59, 130, 246, 0.1)' },
    
    statusIndicator: { width: 10, height: 10, borderRadius: 5, marginRight: 16 },
    statusOn: { backgroundColor: '#10B981', shadowColor: '#10B981', shadowOffset: { width: 0, height: 0 }, shadowOpacity: 0.5, shadowRadius: 5 },
    statusOff: { backgroundColor: '#EF4444' },

    vInfo: { flex: 1 },
    vPlate: { fontSize: 15, fontWeight: '700', color: '#F8FAFC', marginBottom: 2 },
    vDriver: { fontSize: 11, color: '#94A3B8' },

    vSpeedWrap: { alignItems: 'flex-end' },
    vSpeed: { fontSize: 14, fontWeight: '800' },
    vStatus: { fontSize: 10, color: '#64748B', marginTop: 2 }
});
