import React, { useState, useEffect, useContext } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, FlatList, Alert, ActivityIndicator, Platform, Modal } from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import * as Location from 'expo-location';
import api from '../api/axios';
import MaterialCommunityIcons from '@expo/vector-icons/MaterialCommunityIcons';
import { MapView, Marker } from '../components/MapProxy';
import SpaceWaves from '../components/SpaceWaves';

import { AuthContext } from '../context/AuthContext';

export default function PilotCellPointsScreen({ navigation }) {
    const insets = useSafeAreaInsets();
    const { userInfo } = useContext(AuthContext);
    const [isLoading, setIsLoading] = useState(true);
    // ... rest of the state ...
    const [isSaving, setIsSaving] = useState(false);
    const [students, setStudents] = useState([]);
    const [routeInfo, setRouteInfo] = useState(null);

    // Map Modal State
    const [mapModalVisible, setMapModalVisible] = useState(false);
    const [currentLocation, setCurrentLocation] = useState(null);
    const [selectedStudent, setSelectedStudent] = useState(null);
    const [selectedType, setSelectedType] = useState(null); // 'morning' or 'evening'
    const [gpsAccuracy, setGpsAccuracy] = useState(null);

    useEffect(() => {
        fetchStudents();
    }, []);

    const fetchStudents = async () => {
        setIsLoading(true);
        try {
            const res = await api.get('/v1/pilotcell/personnel/my-routes');
            if (res.data?.success && res.data.data && res.data.data.length > 0) {
                const firstRoute = res.data.data[0];
                setRouteInfo(firstRoute);
                setStudents(firstRoute.students || []);
            }
        } catch (error) {
            console.error("Öğrenciler çekilemedi", error);
            Alert.alert('Hata', 'Güzergah bilgileri alınamadı.');
        } finally {
            setIsLoading(false);
        }
    };

    const openPointSelector = async (student, type) => {
        const { status } = await Location.requestForegroundPermissionsAsync();
        if (status !== 'granted') {
            Alert.alert('İzin Reddedildi', 'Nokta belirlemek için uygulamanın konum iznine en yüksek doğrulukla ihtiyacı var.');
            return;
        }

        setIsSaving(true);
        try {
            const location = await Location.getCurrentPositionAsync({
                accuracy: Location.Accuracy.Highest,
            });

            if (location.coords.accuracy > 30) {
                Alert.alert(
                    'Düşük Konum Doğruluğu', 
                    `Cihazınızın GPS doğruluğu şu an düşük (${Math.round(location.coords.accuracy)}m hata payı). Lütfen açık alana çıkın.`
                );
                setIsSaving(false);
                return;
            }

            setCurrentLocation({
                latitude: location.coords.latitude,
                longitude: location.coords.longitude,
                latitudeDelta: 0.002,
                longitudeDelta: 0.002,
            });
            setGpsAccuracy(location.coords.accuracy);
            setSelectedStudent(student);
            setSelectedType(type);
            setMapModalVisible(true);
        } catch (error) {
            console.error(error);
            Alert.alert('Hata', 'Konum alınamadı. Lütfen GPS sinyalinizin güçlü olduğundan emin olun.');
        } finally {
            setIsSaving(false);
        }
    };

    const savePoint = async () => {
        if (!currentLocation || !selectedStudent || !selectedType) return;
        setIsSaving(true);
        try {
            const res = await api.post('/v1/pilotcell/personnel/set-student-point', {
                student_id: selectedStudent.id,
                type: selectedType,
                lat: currentLocation.latitude,
                lng: currentLocation.longitude
            });

            if (res.data?.success) {
                Alert.alert('Başarılı', 'Nokta Belirlendi!');
                setMapModalVisible(false);
                fetchStudents();
            }
        } catch (error) {
            Alert.alert('Hata', 'Nokta kaydedilirken bir hata oluştu.');
        } finally {
            setIsSaving(false);
        }
    };

    const viewOnMap = (student) => {
        navigation.navigate('PilotCellMap', { student });
    };

    const handleBack = () => {
        if (navigation.canGoBack()) {
            navigation.goBack();
        } else {
            if (userInfo?.user_type === 'personnel') {
                navigation.replace('PilotCellDriverMain');
            } else {
                navigation.replace('Menu');
            }
        }
    };

    const renderItem = ({ item }) => {
        const hasMorning = item.morning_lat && item.morning_lng;
        const hasEvening = item.evening_lat && item.evening_lng;
        const hasAny = hasMorning || hasEvening;

        return (
            <View style={styles.card}>
                <View style={styles.cardInfo}>
                    <Text style={styles.studentName}>{item.name}</Text>
                    <Text style={styles.studentSub}>Sınıf: {item.grade || 'Belirtilmemiş'}</Text>
                    
                    <View style={styles.parentRow}>
                        <MaterialCommunityIcons name="account-tie" size={16} color="#94A3B8" />
                        <Text style={styles.parentText} numberOfLines={1} adjustsFontSizeToFit>{item.parent1_name || 'Veli Adı Yok'} - {item.parent1_phone || 'Telefon Yok'}</Text>
                    </View>
                    
                    <View style={styles.statusRow}>
                        {hasMorning ? (
                            <View style={styles.badgeSuccess}><MaterialCommunityIcons name="check-circle" size={12} color="#34D399" /><Text style={styles.badgeText}>Sabah ✓</Text></View>
                        ) : (
                            <View style={styles.badgePending}><MaterialCommunityIcons name="alert-circle" size={12} color="#FBBF24" /><Text style={styles.badgeTextWarning}>Sabah Yok</Text></View>
                        )}
                        {hasEvening ? (
                            <View style={styles.badgeSuccess}><MaterialCommunityIcons name="check-circle" size={12} color="#34D399" /><Text style={styles.badgeText}>Akşam ✓</Text></View>
                        ) : (
                            <View style={styles.badgePending}><MaterialCommunityIcons name="alert-circle" size={12} color="#FBBF24" /><Text style={styles.badgeTextWarning}>Akşam Yok</Text></View>
                        )}
                    </View>
                </View>

                <View style={styles.actionWrap}>
                    <TouchableOpacity style={[styles.btn, styles.btnMorning]} onPress={() => openPointSelector(item, 'morning')}>
                        <MaterialCommunityIcons name="weather-sunny" size={16} color="#FFF" />
                        <Text style={styles.btnText}>Sabah</Text>
                    </TouchableOpacity>

                    <TouchableOpacity style={[styles.btn, styles.btnEvening]} onPress={() => openPointSelector(item, 'evening')}>
                        <MaterialCommunityIcons name="weather-night" size={16} color="#FFF" />
                        <Text style={styles.btnText}>Akşam</Text>
                    </TouchableOpacity>

                    {hasAny && (
                        <TouchableOpacity style={[styles.btn, styles.btnMap]} onPress={() => viewOnMap(item)}>
                            <MaterialCommunityIcons name="map" size={16} color="#A78BFA" />
                            <Text style={styles.btnMapText}>Harita</Text>
                        </TouchableOpacity>
                    )}
                </View>
            </View>
        );
    };

    return (
        <SafeAreaView style={styles.container}>
            <SpaceWaves />
            <View style={styles.header}>
                <TouchableOpacity style={styles.backBtn} onPress={handleBack}>
                    <MaterialCommunityIcons name="arrow-left" size={26} color="#FFF" />
                </TouchableOpacity>
                <Text style={styles.headerTitle}>Öğrenci Noktaları</Text>
                <View style={{ width: 42 }} />
            </View>

            {isLoading ? (
                <View style={styles.loaderWrap}><ActivityIndicator size="large" color="#8B5CF6" /></View>
            ) : (
                <FlatList
                    data={students}
                    keyExtractor={item => item.id.toString()}
                    renderItem={renderItem}
                    contentContainerStyle={styles.listContent}
                    ListEmptyComponent={
                        <View style={styles.emptyWrap}>
                            <MaterialCommunityIcons name="account-search-outline" size={48} color="#64748B" />
                            <Text style={styles.emptyText}>Bu güzergaha kayıtlı öğrenci bulunmuyor.</Text>
                        </View>
                    }
                />
            )}

            {/* Map Modal for Setting Point */}
            <Modal visible={mapModalVisible} animationType="slide" transparent={false}>
                <View style={styles.modalContainer}>
                    <View style={[styles.modalHeader, { paddingTop: insets.top + 15 }]}>
                        <TouchableOpacity onPress={() => setMapModalVisible(false)} style={styles.backBtn}>
                            <MaterialCommunityIcons name="close" size={26} color="#FFF" />
                        </TouchableOpacity>
                        <Text style={styles.modalTitle} numberOfLines={1}>
                            {selectedType === 'morning' ? 'Sabah Konumu' : 'Akşam Konumu'}
                        </Text>
                        <View style={{ width: 42 }} />
                    </View>
                    
                    {currentLocation ? (
                        <MapView
                            style={styles.map}
                            initialRegion={currentLocation}
                            showsUserLocation={true}
                            showsMyLocationButton={true}
                            onRegionChangeComplete={(region) => setCurrentLocation(region)}
                        >
                            <Marker coordinate={currentLocation} />
                        </MapView>
                    ) : (
                        <View style={styles.mapLoader}>
                            <ActivityIndicator size="large" color="#8B5CF6" />
                            <Text style={{color: '#94A3B8', marginTop: 12}}>Harita Yükleniyor...</Text>
                        </View>
                    )}

                    <View style={[styles.modalFooter, { paddingBottom: insets.bottom + 20 }]}>
                        <View style={styles.accuracyBox}>
                            <MaterialCommunityIcons name="crosshairs-gps" size={16} color="#34D399" />
                            <Text style={styles.accuracyText}>GPS Hassasiyeti: {Math.round(gpsAccuracy)}m</Text>
                        </View>
                        <Text style={styles.studentLabel}>{selectedStudent?.name}</Text>
                        
                        <TouchableOpacity style={styles.saveBtn} onPress={savePoint} disabled={isSaving}>
                            {isSaving ? <ActivityIndicator color="#FFF" /> : (
                                <>
                                    <MaterialCommunityIcons name="map-marker-check" size={24} color="#FFF" />
                                    <Text style={styles.saveBtnText}>Noktayı Belirle</Text>
                                </>
                            )}
                        </TouchableOpacity>
                    </View>
                </View>
            </Modal>
        </SafeAreaView>
    );
}

const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#020617' },
    header: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 20, paddingTop: 10, paddingBottom: 15, zIndex: 10 },
    backBtn: { padding: 8, backgroundColor: 'rgba(255,255,255,0.1)', borderRadius: 12 },
    headerTitle: { fontSize: 20, fontWeight: '900', color: '#FFF', letterSpacing: 0.5 },
    loaderWrap: { flex: 1, alignItems: 'center', justifyContent: 'center' },
    listContent: { paddingHorizontal: 20, paddingBottom: 30, paddingTop: 10 },
    card: { backgroundColor: 'rgba(30, 41, 59, 0.7)', borderRadius: 20, padding: 16, marginBottom: 16, flexDirection: 'row', borderWidth: 1, borderColor: 'rgba(139, 92, 246, 0.3)' },
    cardInfo: { flex: 1, paddingRight: 12 },
    studentName: { fontSize: 16, fontWeight: '800', color: '#FFF', marginBottom: 4 },
    studentSub: { fontSize: 13, color: '#94A3B8', marginBottom: 8, fontWeight: '500' },
    parentRow: { flexDirection: 'row', alignItems: 'center', marginBottom: 8 },
    parentText: { fontSize: 11, color: '#94A3B8', marginLeft: 6, fontWeight: '600' },
    statusRow: { flexDirection: 'row', gap: 8, marginTop: 4, flexWrap: 'wrap' },
    badgeSuccess: { flexDirection: 'row', alignItems: 'center', backgroundColor: 'rgba(16, 185, 129, 0.15)', paddingHorizontal: 8, paddingVertical: 4, borderRadius: 8, borderWidth: 1, borderColor: 'rgba(16, 185, 129, 0.3)' },
    badgePending: { flexDirection: 'row', alignItems: 'center', backgroundColor: 'rgba(245, 158, 11, 0.15)', paddingHorizontal: 8, paddingVertical: 4, borderRadius: 8, borderWidth: 1, borderColor: 'rgba(245, 158, 11, 0.3)' },
    badgeText: { fontSize: 10, fontWeight: '800', color: '#34D399', marginLeft: 4 },
    badgeTextWarning: { fontSize: 10, fontWeight: '800', color: '#FBBF24', marginLeft: 4 },
    
    actionWrap: { width: 90, justifyContent: 'center', gap: 8 },
    btn: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', paddingVertical: 8, borderRadius: 10 },
    btnMorning: { backgroundColor: '#0284C7' }, 
    btnEvening: { backgroundColor: '#7C3AED' }, 
    btnMap: { backgroundColor: 'rgba(139, 92, 246, 0.15)', borderWidth: 1, borderColor: 'rgba(139, 92, 246, 0.4)' },
    btnText: { color: '#FFF', fontSize: 11, fontWeight: '800', marginLeft: 4 },
    btnMapText: { color: '#A78BFA', fontSize: 11, fontWeight: '800', marginLeft: 4 },
    
    emptyWrap: { alignItems: 'center', justifyContent: 'center', padding: 40, marginTop: 40, backgroundColor: 'rgba(15, 23, 42, 0.6)', borderRadius: 24, borderWidth: 1, borderColor: 'rgba(255,255,255,0.05)' },
    emptyText: { color: '#94A3B8', marginTop: 16, textAlign: 'center', fontSize: 14, fontWeight: '500', lineHeight: 22 },

    modalContainer: { flex: 1, backgroundColor: '#020617' },
    modalHeader: { backgroundColor: '#0F172A', flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 20, paddingBottom: 15, borderBottomWidth: 1, borderBottomColor: 'rgba(255,255,255,0.05)' },
    modalTitle: { fontSize: 18, fontWeight: '800', color: '#FFF' },
    map: { flex: 1 },
    mapLoader: { flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: '#020617' },
    modalFooter: { backgroundColor: '#0F172A', padding: 20, borderTopWidth: 1, borderTopColor: 'rgba(255,255,255,0.05)' },
    accuracyBox: { flexDirection: 'row', alignItems: 'center', alignSelf: 'center', backgroundColor: 'rgba(16, 185, 129, 0.15)', paddingHorizontal: 16, paddingVertical: 8, borderRadius: 20, marginBottom: 16, borderWidth: 1, borderColor: 'rgba(16, 185, 129, 0.3)' },
    accuracyText: { color: '#34D399', fontWeight: '800', marginLeft: 6, fontSize: 13 },
    studentLabel: { fontSize: 18, fontWeight: '900', textAlign: 'center', color: '#FFF', marginBottom: 20 },
    saveBtn: { backgroundColor: '#8B5CF6', flexDirection: 'row', alignItems: 'center', justifyContent: 'center', paddingVertical: 16, borderRadius: 16, shadowColor: '#8B5CF6', shadowOffset: { width: 0, height: 4 }, shadowOpacity: 0.4, shadowRadius: 8, elevation: 5 },
    saveBtnText: { color: '#FFF', fontSize: 18, fontWeight: '900', marginLeft: 10, letterSpacing: 0.5 }
});
