import React, { useState, useEffect, useRef, useCallback } from 'react';
import { View, Text, StyleSheet, SafeAreaView, ActivityIndicator, Dimensions, Platform, Animated, Vibration } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import MapView, { Marker, AnimatedRegion } from '../components/MapProxy';
import api from '../api/axios';
import SpaceWaves from '../components/SpaceWaves';

const { width, height } = Dimensions.get('window');
const ASPECT_RATIO = width / height;
const LATITUDE_DELTA = 0.01;
const LONGITUDE_DELTA = LATITUDE_DELTA * ASPECT_RATIO;

const TripTimeline = ({ step = 1 }) => {
    const pulseAnim = useRef(new Animated.Value(1)).current;

    useEffect(() => {
        Animated.loop(
            Animated.sequence([
                Animated.timing(pulseAnim, { toValue: 1.15, duration: 800, useNativeDriver: true }),
                Animated.timing(pulseAnim, { toValue: 1, duration: 800, useNativeDriver: true })
            ])
        ).start();
    }, [pulseAnim]);

    const renderNode = (icon, label, nodeStep, color) => {
        const isActive = step === nodeStep;
        const isPassed = step > nodeStep;
        
        return (
            <View style={styles.timelineNodeContainer}>
                <Animated.View style={[
                    styles.timelineNode,
                    { borderColor: isActive || isPassed ? color : '#475569' },
                    isActive && { transform: [{ scale: pulseAnim }] }
                ]}>
                    <MaterialCommunityIcons 
                        name={icon} 
                        size={24} 
                        color={isActive || isPassed ? color : '#475569'} 
                    />
                </Animated.View>
            </View>
        );
    };

    const renderLine = (lineStep) => {
        const isPassed = step > lineStep;
        return (
            <View style={[
                styles.timelineLine,
                { backgroundColor: isPassed ? '#3B82F6' : '#475569' }
            ]} />
        );
    };

    return (
        <View style={styles.timelineContainer}>
            {renderNode('bus-side', 'Servis Başladı', 1, '#EAB308')}
            {renderLine(1)}
            {renderNode('home', 'Araç Yaklaşıyor', 2, '#3B82F6')}
            {renderLine(2)}
            {renderNode('school', 'Okula Yaklaştı', 3, '#22C55E')}
        </View>
    );
};

export default function ParentHomeScreen() {
    const [isLoading, setIsLoading] = useState(true);
    const [activeTrip, setActiveTrip] = useState(null);
    const [vehicleLocation, setVehicleLocation] = useState(null);
    const [studentLocation, setStudentLocation] = useState(null);
    const [studentAttendance, setStudentAttendance] = useState(null);
    const [lastUpdated, setLastUpdated] = useState(null);
    const mapRef = useRef(null);
    const markerRef = useRef(null);
    const pollIntervalRef = useRef(null);
    const hasCenteredMapRef = useRef(false);
    const prevAttendanceRef = useRef(null);

    const coordinateRef = useRef(
        new AnimatedRegion({
            latitude: 39.92077, // Fallback center (Ankara)
            longitude: 32.85411,
            latitudeDelta: LATITUDE_DELTA,
            longitudeDelta: LONGITUDE_DELTA,
        })
    );

    const fetchActiveTrip = async () => {
        try {
            const res = await api.get('/v1/pilotcell/parent/active-trip');
            if (res.data?.success && res.data.trip) {
                // If trip changes or is found for the first time, allow recentering
                if (!activeTrip || activeTrip.id !== res.data.trip.id) {
                    hasCenteredMapRef.current = false;
                }
                setActiveTrip(res.data.trip);
                
                if (res.data.student_location?.lat) {
                    setStudentLocation({
                        latitude: parseFloat(res.data.student_location.lat),
                        longitude: parseFloat(res.data.student_location.lng)
                    });
                }

                return res.data.trip.id;
            } else {
                setActiveTrip(null);
                setVehicleLocation(null);
                setStudentLocation(null);
                hasCenteredMapRef.current = false;
                return null;
            }
        } catch (error) {
            console.log('Error fetching active trip:', error);
            return null;
        }
    };

    const fetchLatestLocation = useCallback(async (tripId) => {
        try {
            const res = await api.get(`/v1/pilotcell/location/latest/${tripId}`);
            if (res.data?.success && res.data.data) {
                const loc = res.data.data;
                
                // Check if trip ended
                if (loc.status === 'completed') {
                    setActiveTrip(null);
                    setVehicleLocation(null);
                    hasCenteredMapRef.current = false;
                    if (pollIntervalRef.current) {
                        clearInterval(pollIntervalRef.current);
                    }
                    startPolling(); // Restart the check loop
                    return;
                }

                if (loc.student_attendance) {
                    // Check if attendance changed to trigger a notification
                    if (prevAttendanceRef.current !== JSON.stringify(loc.student_attendance)) {
                        if (prevAttendanceRef.current !== null) {
                            // Play vibration if it's a new update (not the first load)
                            Platform.OS === 'ios' ? Vibration.vibrate() : Vibration.vibrate([0, 500, 200, 500]);
                        }
                        prevAttendanceRef.current = JSON.stringify(loc.student_attendance);
                        setStudentAttendance(loc.student_attendance);
                    }
                }

                if (!loc.lat || !loc.lng) return;

                const newCoordinate = {
                    latitude: parseFloat(loc.lat),
                    longitude: parseFloat(loc.lng),
                    latitudeDelta: LATITUDE_DELTA,
                    longitudeDelta: LONGITUDE_DELTA,
                };

                setVehicleLocation({
                    ...loc,
                    lat: parseFloat(loc.lat),
                    lng: parseFloat(loc.lng),
                    heading: parseFloat(loc.heading) || 0,
                    speed: parseFloat(loc.speed) || 0
                });

                setLastUpdated(new Date().toLocaleTimeString('tr-TR'));

                // Animate marker
                if (Platform.OS === 'android') {
                    if (markerRef.current) {
                        markerRef.current.animateMarkerToCoordinate(newCoordinate, 1000);
                    }
                } else {
                    coordinateRef.current.timing({
                        ...newCoordinate,
                        duration: 1000,
                        useNativeDriver: false
                    }).start();
                }

                // Smoothly follow vehicle with map ONLY ONCE to allow parent to freely look around
                if (mapRef.current && !hasCenteredMapRef.current) {
                    mapRef.current.animateToRegion(newCoordinate, 1000);
                    hasCenteredMapRef.current = true;
                }
            }
        } catch (error) {
            console.log('Error fetching location:', error);
        }
    }, []);

    const startPolling = useCallback(async () => {
        setIsLoading(true);
        const tripId = await fetchActiveTrip();
        setIsLoading(false);

        if (tripId) {
            // Fetch immediately
            await fetchLatestLocation(tripId);
            
            // Then poll every 3 seconds
            pollIntervalRef.current = setInterval(() => {
                fetchLatestLocation(tripId);
            }, 3000);
        } else {
            // Check again for active trip every 10 seconds if none currently active
            pollIntervalRef.current = setInterval(async () => {
                const newTripId = await fetchActiveTrip();
                if (newTripId) {
                    clearInterval(pollIntervalRef.current);
                    startPolling();
                }
            }, 10000);
        }
    }, [fetchLatestLocation]);

    useEffect(() => {
        startPolling();
        return () => {
            if (pollIntervalRef.current) {
                clearInterval(pollIntervalRef.current);
            }
        };
    }, [startPolling]);

    if (isLoading && !activeTrip) {
        return (
            <SafeAreaView style={styles.container}>
                <SpaceWaves />
                <View style={styles.loadingContainer}>
                    <ActivityIndicator size="large" color="#8B5CF6" />
                    <Text style={styles.loadingText}>Bağlantı kuruluyor...</Text>
                </View>
            </SafeAreaView>
        );
    }

    return (
        <SafeAreaView style={styles.container}>
            <SpaceWaves />

            {/* Header */}
            <View style={styles.header}>
                <Text style={styles.headerTitle}>Canlı Takip</Text>
                {activeTrip && vehicleLocation && (
                    <View style={styles.liveBadge}>
                        <View style={styles.liveDot} />
                        <Text style={styles.liveText}>CANLI</Text>
                    </View>
                )}
            </View>

            {activeTrip && <TripTimeline step={1} />}

            {!activeTrip ? (
                <View style={styles.emptyState}>
                    <MaterialCommunityIcons name="bus-alert" size={64} color="#64748B" />
                    <Text style={styles.emptyTitle}>Aktif Sefer Yok</Text>
                    <Text style={styles.emptyText}>
                        Öğrencinizin servisi şu anda aktif bir seferde bulunmuyor. Sefer başladığında takip ekranı otomatik olarak açılacaktır.
                    </Text>
                </View>
            ) : (
                <View style={styles.mapContainer}>
                    <MapView
                        ref={mapRef}
                        style={styles.map}
                        initialRegion={
                            vehicleLocation
                                ? {
                                      latitude: vehicleLocation.lat,
                                      longitude: vehicleLocation.lng,
                                      latitudeDelta: LATITUDE_DELTA,
                                      longitudeDelta: LONGITUDE_DELTA,
                                  }
                                : {
                                      latitude: 39.92077,
                                      longitude: 32.85411,
                                      latitudeDelta: LATITUDE_DELTA,
                                      longitudeDelta: LONGITUDE_DELTA,
                                  }
                        }
                        customMapStyle={mapStyle}
                    >
                        {vehicleLocation && (
                            <Marker.Animated
                                ref={markerRef}
                                coordinate={coordinateRef.current}
                                anchor={{ x: 0.5, y: 0.5 }}
                                rotation={vehicleLocation.heading || 0}
                            >
                                <View style={styles.markerWrap}>
                                    <View style={styles.markerHalo} />
                                    <MaterialCommunityIcons name="bus" size={24} color="#FFF" style={styles.markerIcon} />
                                </View>
                            </Marker.Animated>
                        )}
                        {studentLocation && (
                            <Marker
                                coordinate={{ latitude: studentLocation.latitude, longitude: studentLocation.longitude }}
                                title="Öğrenci Konumu"
                                anchor={{ x: 0.5, y: 0.5 }}
                            >
                                <View style={styles.studentMarkerWrap}>
                                    <View style={styles.studentMarkerHalo} />
                                    <View style={styles.studentMarkerIcon}>
                                        <MaterialCommunityIcons name={activeTrip.direction === 'morning' ? 'home' : 'school'} size={18} color="#FFF" style={{ textAlign: 'center', lineHeight: 24 }} />
                                    </View>
                                </View>
                            </Marker>
                        )}
                    </MapView>

                    {/* Bottom Info Card */}
                    <View style={styles.infoCard}>
                        <View style={styles.infoRow}>
                            <View style={styles.iconWrap}>
                                <MaterialCommunityIcons name="bus-school" size={28} color="#A78BFA" />
                            </View>
                            <View style={{ flex: 1 }}>
                                <Text style={styles.plateText}>{activeTrip.vehicle?.plate}</Text>
                                <Text style={styles.routeText}>Servis Yolda</Text>
                            </View>
                            <View style={styles.speedWrap}>
                                <Text style={styles.speedText}>{vehicleLocation?.speed ? Math.round(vehicleLocation.speed) : 0}</Text>
                                <Text style={styles.speedUnit}>km/s</Text>
                            </View>
                        </View>

                        {studentAttendance?.boarded_at && (
                            <View style={styles.attendanceRow}>
                                <MaterialCommunityIcons name="bus-door" size={16} color="#10B981" />
                                <Text style={styles.attendanceText}>Öğrenci araca bindi ({new Date(studentAttendance.boarded_at).toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit', second: '2-digit' })})</Text>
                            </View>
                        )}
                        {studentAttendance?.alighted_at && (
                            <View style={[styles.attendanceRow, { backgroundColor: 'rgba(59, 130, 246, 0.1)', borderColor: 'rgba(59, 130, 246, 0.2)' }]}>
                                <MaterialCommunityIcons name="home-export-outline" size={16} color="#3B82F6" />
                                <Text style={[styles.attendanceText, { color: '#3B82F6' }]}>Öğrenci araçtan indi ({new Date(studentAttendance.alighted_at).toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit', second: '2-digit' })})</Text>
                            </View>
                        )}

                        <View style={styles.lastUpdateRow}>
                            <MaterialCommunityIcons name="clock-outline" size={14} color="#94A3B8" />
                            <Text style={styles.lastUpdateText}>Son konum: {lastUpdated || 'Bekleniyor...'}</Text>
                        </View>
                    </View>
                </View>
            )}
        </SafeAreaView>
    );
}

const mapStyle = [
    { elementType: "geometry", stylers: [{ color: "#242f3e" }] },
    { elementType: "labels.text.fill", stylers: [{ color: "#746855" }] },
    { elementType: "labels.text.stroke", stylers: [{ color: "#242f3e" }] },
    { featureType: "administrative.locality", elementType: "labels.text.fill", stylers: [{ color: "#d59563" }] },
    { featureType: "poi", elementType: "labels.text.fill", stylers: [{ color: "#d59563" }] },
    { featureType: "road", elementType: "geometry", stylers: [{ color: "#38414e" }] },
    { featureType: "road", elementType: "geometry.stroke", stylers: [{ color: "#212a37" }] },
    { featureType: "road", elementType: "labels.text.fill", stylers: [{ color: "#9ca5b3" }] },
    { featureType: "road.highway", elementType: "geometry", stylers: [{ color: "#746855" }] },
    { featureType: "road.highway", elementType: "geometry.stroke", stylers: [{ color: "#1f2835" }] },
    { featureType: "road.highway", elementType: "labels.text.fill", stylers: [{ color: "#f3d19c" }] },
    { featureType: "transit", elementType: "geometry", stylers: [{ color: "#2f3948" }] },
    { featureType: "water", elementType: "geometry", stylers: [{ color: "#17263c" }] },
    { featureType: "water", elementType: "labels.text.fill", stylers: [{ color: "#515c6d" }] },
    { featureType: "water", elementType: "labels.text.stroke", stylers: [{ color: "#17263c" }] }
];

const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#020617' },
    loadingContainer: { flex: 1, justifyContent: 'center', alignItems: 'center' },
    loadingText: { color: '#94A3B8', marginTop: 12, fontSize: 14, fontWeight: '600' },
    
    header: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', paddingHorizontal: 20, paddingTop: Platform.OS === 'ios' ? 10 : 30, paddingBottom: 15, zIndex: 10 },
    headerTitle: { fontSize: 24, fontWeight: '900', color: '#FFF', letterSpacing: 0.5 },
    liveBadge: { flexDirection: 'row', alignItems: 'center', backgroundColor: 'rgba(239, 68, 68, 0.2)', paddingHorizontal: 12, paddingVertical: 6, borderRadius: 20, borderWidth: 1, borderColor: 'rgba(239, 68, 68, 0.5)' },
    liveDot: { width: 8, height: 8, borderRadius: 4, backgroundColor: '#EF4444', marginRight: 6 },
    liveText: { color: '#EF4444', fontSize: 12, fontWeight: '800' },

    timelineContainer: { marginHorizontal: 20, marginBottom: 20, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', backgroundColor: 'rgba(30, 41, 59, 0.95)', paddingVertical: 12, paddingHorizontal: 20, borderRadius: 30, zIndex: 20, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)' },
    timelineNodeContainer: { alignItems: 'center', justifyContent: 'center' },
    timelineNode: { width: 44, height: 44, borderRadius: 22, borderWidth: 2, alignItems: 'center', justifyContent: 'center', backgroundColor: '#0F172A' },
    timelineLine: { flex: 1, height: 4, marginHorizontal: 8, borderRadius: 2 },

    emptyState: { flex: 1, justifyContent: 'center', alignItems: 'center', paddingHorizontal: 30 },
    emptyTitle: { color: '#FFF', fontSize: 20, fontWeight: '800', marginTop: 16, marginBottom: 8 },
    emptyText: { color: '#94A3B8', fontSize: 14, textAlign: 'center', lineHeight: 22 },

    mapContainer: { flex: 1, position: 'relative', overflow: 'hidden', borderTopLeftRadius: 30, borderTopRightRadius: 30 },
    map: { ...StyleSheet.absoluteFillObject },

    markerWrap: { alignItems: 'center', justifyContent: 'center', width: 50, height: 50 },
    markerHalo: { position: 'absolute', width: 40, height: 40, borderRadius: 20, backgroundColor: 'rgba(139, 92, 246, 0.3)', borderWidth: 1, borderColor: 'rgba(139, 92, 246, 0.8)' },
    markerIcon: { backgroundColor: '#8B5CF6', width: 28, height: 28, borderRadius: 14, textAlign: 'center', textAlignVertical: 'center', overflow: 'hidden' },

    studentMarkerWrap: { alignItems: 'center', justifyContent: 'center', width: 40, height: 40 },
    studentMarkerHalo: { position: 'absolute', width: 34, height: 34, borderRadius: 17, backgroundColor: 'rgba(59, 130, 246, 0.3)', borderWidth: 1, borderColor: 'rgba(59, 130, 246, 0.8)' },
    studentMarkerIcon: { backgroundColor: '#3B82F6', width: 24, height: 24, borderRadius: 12, overflow: 'hidden' },

    infoCard: { position: 'absolute', bottom: 30, left: 20, right: 20, backgroundColor: 'rgba(15, 23, 42, 0.95)', borderRadius: 20, padding: 20, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', shadowColor: '#000', shadowOffset: { width: 0, height: 10 }, shadowopacity: 1, shadowRadius: 20, elevation: 10 },
    infoRow: { flexDirection: 'row', alignItems: 'center', marginBottom: 12 },
    iconWrap: { backgroundColor: 'rgba(167, 139, 250, 0.2)', padding: 12, borderRadius: 16, marginRight: 16 },
    plateText: { color: '#FFF', fontSize: 20, fontWeight: '900' },
    routeText: { color: '#34D399', fontSize: 13, fontWeight: '700', marginTop: 2 },
    speedWrap: { alignItems: 'center', justifyContent: 'center', backgroundColor: 'rgba(255,255,255,0.05)', paddingHorizontal: 16, paddingVertical: 10, borderRadius: 16 },
    speedText: { color: '#FFF', fontSize: 24, fontWeight: '900' },
    speedUnit: { color: '#94A3B8', fontSize: 10, fontWeight: '700', marginTop: -2 },

    attendanceRow: { flexDirection: 'row', alignItems: 'center', backgroundColor: 'rgba(16, 185, 129, 0.1)', paddingHorizontal: 12, paddingVertical: 8, borderRadius: 8, marginBottom: 12, borderWidth: 1, borderColor: 'rgba(16, 185, 129, 0.2)' },
    attendanceText: { color: '#10B981', fontSize: 13, fontWeight: '700', marginLeft: 8 },

    lastUpdateRow: { flexDirection: 'row', alignItems: 'center', borderTopWidth: 1, borderTopColor: 'rgba(255,255,255,0.05)', paddingTop: 12 },
    lastUpdateText: { color: '#94A3B8', fontSize: 12, fontWeight: '500', marginLeft: 6 },
});
