import React, { useState, useEffect, useContext } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, FlatList, Alert, Switch, Platform, Modal, ScrollView, BackHandler } from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import * as Location from 'expo-location';
import api from '../api/axios';
import { AuthContext } from '../context/AuthContext';
import MaterialCommunityIcons from '@expo/vector-icons/MaterialCommunityIcons';
import { CONFIG } from '../config';
import SpaceWaves from '../components/SpaceWaves';

const PilotCellDriverScreen = ({ navigation }) => {
    const insets = useSafeAreaInsets();
    const { userInfo: user, hasPermission, logout } = useContext(AuthContext);
    const [isTracking, setIsTracking] = useState(false);
    const [myRoutes, setMyRoutes] = useState([]);
    const [activeTrip, setActiveTrip] = useState(null);
    const [students, setStudents] = useState([]);
    const [schools, setSchools] = useState([]);
    const [locationSubscription, setLocationSubscription] = useState(null);
    const [menuVisible, setMenuVisible] = useState(false);
    const [isLoading, setIsLoading] = useState(false);

    // Initial check for active trip and back handler
    useEffect(() => {
        fetchActiveTrip();
        
        // Android Donanımsal Geri Tuşunu Yakalama (GO_BACK hatasını önler)
        const backAction = () => {
            Alert.alert("Uygulamadan Çık", "ServisPilot uygulamasından çıkmak istiyor musunuz?", [
                {
                    text: "İptal",
                    onPress: () => null,
                    style: "cancel"
                },
                { text: "Çıkış Yap", onPress: () => BackHandler.exitApp(), style: 'destructive' }
            ]);
            return true;
        };

        const backHandler = BackHandler.addEventListener(
            "hardwareBackPress",
            backAction
        );

        return () => {
            stopTracking();
            backHandler.remove();
        };
    }, []);

    // Schools are now derived from myRoutes customer data
    useEffect(() => {
        if (myRoutes.length > 0) {
            const uniqueSchools = [];
            const seenIds = new Set();
            myRoutes.forEach(route => {
                if (route.customer && !seenIds.has(route.customer.id)) {
                    seenIds.add(route.customer.id);
                    uniqueSchools.push(route.customer);
                }
            });
            setSchools(uniqueSchools);
        }
    }, [myRoutes]);

    const fetchActiveTrip = async () => {
        setIsLoading(true);
        try {
            // My Routes (Personnel'e atanmış güzergahlar)
            const routeRes = await api.get('/v1/pilotcell/personnel/my-routes');
            if (routeRes.data?.success) {
                setMyRoutes(routeRes.data.data || []);
                // Eğer atanmış güzergahı varsa ve içinde öğrenciler varsa, şimdilik ilkini gösterelim
                if (routeRes.data.data && routeRes.data.data.length > 0) {
                    const firstRoute = routeRes.data.data[0];
                    setStudents(firstRoute.students || []);
                }
            }

            // Aktif seferi bulma
            const response = await api.get('/v1/pilotcell/trips/active');
            const trips = response.data?.data || [];
            
            const myTrip = trips.find(t => t.driver_id === user?.personnel_id);
            if (myTrip) {
                setActiveTrip(myTrip);
                setStudents(myTrip.route?.students || []);
            }
        } catch (error) {
            console.error("Aktif sefer veya güzergah çekilemedi", error);
        } finally {
            setIsLoading(false);
        }
    };

    const startTracking = async () => {
        if (!activeTrip) {
            Alert.alert("Hata", "Aktif bir seferiniz bulunmuyor.");
            return;
        }

        const { status } = await Location.requestForegroundPermissionsAsync();
        if (status !== 'granted') {
            Alert.alert('İzin Gerekli', 'Konum izni verilmeden takip başlatılamaz.');
            return;
        }

        setIsTracking(true);

        const sub = await Location.watchPositionAsync(
            {
                accuracy: Location.Accuracy.High,
                timeInterval: 5000, // Her 5 saniyede bir
                distanceInterval: 10, // veya her 10 metrede bir
            },
            async (location) => {
                // SIFIR TOLERANSLI LOKASYON DOĞRULUĞU (20 metre üstü kabul edilmez)
                if (location.coords.accuracy > 20) {
                    console.log(`[PilotCell] Düşük Hassasiyet (${location.coords.accuracy}m). Atlanıyor.`);
                    return;
                }

                try {
                    await api.post('/v1/pilotcell/location/update', {
                        trip_id: activeTrip.id,
                        lat: location.coords.latitude,
                        lng: location.coords.longitude,
                        accuracy: location.coords.accuracy,
                        speed: (location.coords.speed || 0) * 3.6, // m/s to km/h
                        heading: location.coords.heading || 0,
                        recorded_at: new Date(location.timestamp).toISOString(),
                    });
                    console.log("[PilotCell] Konum gönderildi: ", location.coords.latitude, location.coords.longitude);
                } catch (error) {
                    console.error("[PilotCell] Konum gönderimi başarısız", error);
                }
            }
        );
        setLocationSubscription(sub);
    };

    const stopTracking = () => {
        if (locationSubscription) {
            locationSubscription.remove();
            setLocationSubscription(null);
        }
        setIsTracking(false);
    };

    const toggleTracking = () => {
        if (isTracking) {
            stopTracking();
        } else {
            startTracking();
        }
    };

    const markAttendance = async (studentId, status) => {
        try {
            await api.post('/v1/pilotcell/attendance/update', {
                trip_id: activeTrip.id,
                student_id: studentId,
                status: status,
                timestamp: new Date().toISOString()
            });
            Alert.alert("Başarılı", "Yoklama kaydedildi.");
        } catch (error) {
            console.error("Yoklama kaydedilemedi", error);
            Alert.alert("Hata", "Yoklama kaydedilirken bir sorun oluştu.");
        }
    };

    const renderStudentItem = ({ item }) => (
        <View style={styles.studentCard}>
            <Text style={styles.studentName}>{item.name}</Text>
            <View style={styles.attendanceButtons}>
                <TouchableOpacity style={[styles.btn, styles.btnBoarded]} onPress={() => markAttendance(item.id, 'boarded')}>
                    <Text style={styles.btnText}>Bindi</Text>
                </TouchableOpacity>
                <TouchableOpacity style={[styles.btn, styles.btnAlighted]} onPress={() => markAttendance(item.id, 'alighted')}>
                    <Text style={styles.btnText}>İndi</Text>
                </TouchableOpacity>
                <TouchableOpacity style={[styles.btn, styles.btnAbsent]} onPress={() => markAttendance(item.id, 'absent')}>
                    <Text style={styles.btnText}>Gelmedi</Text>
                </TouchableOpacity>
            </View>
        </View>
    );

    return (
        <SafeAreaView style={styles.container}>
            <SpaceWaves />
            <View style={styles.header}>
                <TouchableOpacity onPress={() => setMenuVisible(true)} style={styles.menuIconWrap}>
                    <MaterialCommunityIcons name="menu" size={28} color="white" />
                </TouchableOpacity>
                <Text style={styles.headerTitle}>Şoför Paneli</Text>
                <View style={{ width: 44 }} />
            </View>

            {/* Menu Drawer Modal */}
            <Modal visible={menuVisible} animationType="fade" transparent={true}>
                 <TouchableOpacity style={styles.drawerOverlay} activeOpacity={1} onPress={() => setMenuVisible(false)}>
                      <View style={styles.drawerContent} onStartShouldSetResponder={() => true}>
                            <View style={[styles.drawerHeader, { paddingTop: insets.top + 25 }]}>
                                <View style={styles.drawerAvatar}>
                                    <MaterialCommunityIcons name="account" size={36} color="#FFF" />
                                </View>
                                <Text style={styles.drawerName}>{user?.name || 'Personel'}</Text>
                                <Text style={styles.drawerRole}>{user?.username || ''}</Text>
                            </View>
                            
                            <ScrollView style={styles.drawerBody}>
                                <TouchableOpacity style={styles.drawerItem} onPress={() => {setMenuVisible(false);}}>
                                    <View style={styles.drawerIconBox}>
                                        <MaterialCommunityIcons name="bus" size={22} color="#FFF" />
                                    </View>
                                    <Text style={styles.drawerItemText}>Aktif Seferim</Text>
                                </TouchableOpacity>
                                <TouchableOpacity style={styles.drawerItem} onPress={() => {setMenuVisible(false); navigation.navigate('PilotCellPoints');}}>
                                    <View style={[styles.drawerIconBox, {backgroundColor: 'rgba(16, 185, 129, 0.2)'}]}>
                                        <MaterialCommunityIcons name="map-marker-path" size={22} color="#34D399" />
                                    </View>
                                    <Text style={styles.drawerItemText}>Nokta Belirleme</Text>
                                </TouchableOpacity>
                                <TouchableOpacity style={styles.drawerItem} onPress={() => {setMenuVisible(false); navigation.navigate('PilotCellRadius');}}>
                                    <View style={[styles.drawerIconBox, {backgroundColor: 'rgba(245, 158, 11, 0.2)'}]}>
                                        <MaterialCommunityIcons name="radar" size={22} color="#FBBF24" />
                                    </View>
                                    <Text style={styles.drawerItemText}>Mesafe Belirleme</Text>
                                </TouchableOpacity>
                            </ScrollView>

                            <TouchableOpacity style={[styles.drawerLogout, { paddingBottom: insets.bottom + 25 }]} onPress={() => {setMenuVisible(false); logout();}}>
                                <MaterialCommunityIcons name="logout" size={24} color="#EF4444" />
                                <Text style={styles.drawerLogoutText}>Çıkış Yap</Text>
                            </TouchableOpacity>
                      </View>
                 </TouchableOpacity>
            </Modal>


            {activeTrip ? (
                <>
                    <View style={styles.trackingContainer}>
                        <View style={styles.trackingInfo}>
                            <View style={[styles.trackingIconWrap, isTracking && {backgroundColor: 'rgba(16, 185, 129, 0.2)', borderColor: 'rgba(16, 185, 129, 0.5)'}]}>
                                <MaterialCommunityIcons name="bus" size={28} color={isTracking ? "#34D399" : "#94A3B8"} />
                            </View>
                            <View style={styles.trackingTexts}>
                                <Text style={styles.routeText}>{activeTrip.route?.name}</Text>
                                <Text style={styles.statusText}>{isTracking ? "Konum Yayınlanıyor..." : "Takip Kapalı"}</Text>
                            </View>
                        </View>
                        <Switch
                            value={isTracking}
                            onValueChange={toggleTracking}
                            trackColor={{ false: "rgba(255,255,255,0.1)", true: "rgba(16, 185, 129, 0.3)" }}
                            thumbColor={isTracking ? "#34D399" : "#94A3B8"}
                        />
                    </View>

                    <Text style={styles.listTitle}>Öğrenci Yoklaması ({students.length})</Text>
                    {students.length > 0 ? (
                        <FlatList
                            data={students}
                            keyExtractor={(item) => item.id.toString()}
                            renderItem={renderStudentItem}
                            contentContainerStyle={{ paddingBottom: 20 }}
                        />
                    ) : (
                        <View style={styles.emptyContainer}>
                            <Text style={styles.emptyText}>Güzergaha kayıtlı öğrenci bulunamadı.</Text>
                        </View>
                    )}
                </>
            ) : myRoutes.length > 0 ? (
                <>
                    <View style={styles.emptyBigContainer}>
                        <View style={{flexDirection: 'row', alignItems: 'center', marginBottom: 12}}>
                            <View style={{backgroundColor: 'rgba(167, 139, 250, 0.2)', padding: 12, borderRadius: 16, marginRight: 16}}>
                                <MaterialCommunityIcons name="bus-school" size={32} color="#A78BFA" />
                            </View>
                            <View style={{flex: 1}}>
                                <Text style={{color: '#94A3B8', fontSize: 13, fontWeight: '600'}}>{myRoutes[0]?.customer?.company_name || 'Okul Tanımsız'}</Text>
                                <Text style={{color: '#FFF', fontSize: 20, fontWeight: '800', marginVertical: 4}} numberOfLines={1}>{myRoutes[0]?.name || 'Güzergah'}</Text>
                                <Text style={{color: '#34D399', fontSize: 13, fontWeight: '700'}}>{myRoutes[0]?.vehicle?.plate || 'Araç Atanmamış'}</Text>
                            </View>
                        </View>
                        
                        <View style={{height: 1, backgroundColor: 'rgba(255,255,255,0.1)', marginVertical: 12}} />
                        
                        <Text style={{color: '#94A3B8', fontSize: 12, textAlign: 'center', marginBottom: 16}}>Seferinizi başlatmak için aşağıdaki butonlardan birini seçin.</Text>

                        <View style={{flexDirection: 'row', gap: 10}}>
                            <TouchableOpacity style={[styles.btn, styles.btnStartTrip, {backgroundColor: '#0284C7', flex: 1, paddingVertical: 14}]} onPress={() => navigation.navigate('PilotCellTrip', { route: myRoutes[0], direction: 'morning' })}>
                                <MaterialCommunityIcons name="weather-sunny" size={20} color="#FFF" style={{marginRight: 6}} />
                                <Text style={styles.btnText}>Sabah Servisini Başlat</Text>
                            </TouchableOpacity>
                            <TouchableOpacity style={[styles.btn, styles.btnStartTrip, {backgroundColor: '#7C3AED', flex: 1, paddingVertical: 14}]} onPress={() => navigation.navigate('PilotCellTrip', { route: myRoutes[0], direction: 'evening' })}>
                                <MaterialCommunityIcons name="weather-night" size={20} color="#FFF" style={{marginRight: 6}} />
                                <Text style={styles.btnText}>Akşam Servisini Başlat</Text>
                            </TouchableOpacity>
                        </View>
                    </View>
                </>
            ) : (
                <View style={styles.emptyBigContainer}>
                    <View style={styles.emptyIconCircle}>
                        <MaterialCommunityIcons name="bus-alert" size={48} color="#94A3B8" />
                    </View>
                    <Text style={styles.emptyBigTitle}>Güzergah Yok</Text>
                    <Text style={styles.emptyBigText}>Size tanımlı herhangi bir güzergah bulunmuyor. Lütfen yönetici ile iletişime geçin.</Text>
                </View>
            )}
        </SafeAreaView>
    );
};

const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#020617' },
    header: { paddingHorizontal: 20, paddingTop: 10, paddingBottom: 15, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', zIndex: 10 },
    menuIconWrap: { padding: 8, backgroundColor: 'rgba(255,255,255,0.1)', borderRadius: 12 },
    headerTitle: { color: 'white', fontSize: 20, fontWeight: '900', letterSpacing: 0.5 },
    
    // DRAWER STYLES
    drawerOverlay: { flex: 1, backgroundColor: 'rgba(2, 6, 23, 0.8)', justifyContent: 'flex-start' },
    drawerContent: { width: '75%', height: '100%', backgroundColor: '#0F172A', borderTopRightRadius: 30, borderBottomRightRadius: 30, overflow: 'hidden', borderWidth: 1, borderColor: 'rgba(255,255,255,0.05)' },
    drawerHeader: { backgroundColor: 'rgba(139, 92, 246, 0.1)', padding: 25, alignItems: 'center', borderBottomWidth: 1, borderBottomColor: 'rgba(255,255,255,0.05)' },
    drawerAvatar: { width: 72, height: 72, borderRadius: 36, backgroundColor: '#8B5CF6', alignItems: 'center', justifyContent: 'center', marginBottom: 12, shadowColor: '#8B5CF6', shadowOffset: { width: 0, height: 4 }, shadowOpacity: 1, shadowRadius: 10, elevation: 5 },
    drawerName: { color: 'white', fontSize: 18, fontWeight: '900', letterSpacing: 0.5 },
    drawerRole: { color: '#94A3B8', fontSize: 13, marginTop: 4, fontWeight: '600' },
    drawerBody: { flex: 1, paddingTop: 20 },
    drawerItem: { flexDirection: 'row', alignItems: 'center', paddingVertical: 16, paddingHorizontal: 25, marginHorizontal: 15, marginBottom: 10, borderRadius: 16, backgroundColor: 'rgba(30, 41, 59, 0.5)', borderWidth: 1, borderColor: 'rgba(255,255,255,0.05)' },
    drawerIconBox: { width: 36, height: 36, borderRadius: 10, backgroundColor: 'rgba(139, 92, 246, 0.2)', alignItems: 'center', justifyContent: 'center' },
    drawerItemText: { fontSize: 15, fontWeight: '800', color: '#E2E8F0', marginLeft: 16 },
    drawerLogout: { flexDirection: 'row', alignItems: 'center', padding: 25, borderTopWidth: 1, borderTopColor: 'rgba(255,255,255,0.05)', backgroundColor: 'rgba(239, 68, 68, 0.05)' },
    drawerLogoutText: { fontSize: 16, fontWeight: '900', color: '#EF4444', marginLeft: 16 },
    
    listTitle: { fontSize: 16, fontWeight: '800', color: '#E2E8F0', marginHorizontal: 20, marginBottom: 15, marginTop: 15, letterSpacing: 0.5 },
    
    // SCHOOLS
    schoolsSection: { marginBottom: 10 },
    schoolsScroll: { paddingHorizontal: 20, paddingBottom: 10, gap: 12 },
    schoolCard: {
        flexDirection: 'row', alignItems: 'center', backgroundColor: 'rgba(30, 41, 59, 0.7)', padding: 12, borderRadius: 16,
        minWidth: 220, borderWidth: 1, borderColor: 'rgba(139, 92, 246, 0.3)'
    },
    schoolIconWrap: { width: 44, height: 44, borderRadius: 12, backgroundColor: 'rgba(139, 92, 246, 0.15)', alignItems: 'center', justifyContent: 'center', marginRight: 12, borderWidth: 1, borderColor: 'rgba(139, 92, 246, 0.3)' },
    schoolName: { fontSize: 14, fontWeight: '800', color: '#FFF' },
    schoolSub: { fontSize: 11, color: '#94A3B8', marginTop: 4, fontWeight: '600' },
    emptySchools: { flexDirection: 'row', alignItems: 'center', marginHorizontal: 20, padding: 16, backgroundColor: 'rgba(30, 41, 59, 0.5)', borderRadius: 16, borderWidth: 1, borderColor: 'rgba(255,255,255,0.05)' },
    emptySchoolsText: { color: '#94A3B8', fontSize: 13, fontWeight: '600', flex: 1 },
    
    // TRACKING
    trackingContainer: { 
        flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', 
        backgroundColor: 'rgba(30, 41, 59, 0.8)', marginHorizontal: 20, marginTop: 10, marginBottom: 15, padding: 16, borderRadius: 20,
        borderWidth: 1, borderColor: 'rgba(139, 92, 246, 0.4)'
    },
    trackingInfo: { flexDirection: 'row', alignItems: 'center', flex: 1 },
    trackingIconWrap: { width: 48, height: 48, borderRadius: 14, backgroundColor: 'rgba(255,255,255,0.05)', alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)' },
    trackingTexts: { marginLeft: 12, flex: 1 },
    routeText: { fontSize: 16, fontWeight: '900', color: '#FFF', letterSpacing: 0.5 },
    statusText: { fontSize: 13, color: '#94A3B8', marginTop: 4, fontWeight: '600' },
    
    // STUDENTS
    studentCard: { 
        backgroundColor: 'rgba(30, 41, 59, 0.6)', marginHorizontal: 20, marginBottom: 12, padding: 16, 
        borderRadius: 16, borderLeftWidth: 4, borderLeftColor: '#8B5CF6',
        borderWidth: 1, borderColor: 'rgba(255,255,255,0.05)'
    },
    studentName: { fontSize: 16, fontWeight: '800', color: '#FFF', marginBottom: 12 },
    attendanceButtons: { flexDirection: 'row', gap: 10 },
    btn: { flex: 1, paddingVertical: 10, borderRadius: 10, alignItems: 'center', flexDirection: 'row', justifyContent: 'center' },
    btnBoarded: { backgroundColor: 'rgba(16, 185, 129, 0.15)', borderWidth: 1, borderColor: 'rgba(16, 185, 129, 0.4)' },
    btnAlighted: { backgroundColor: 'rgba(245, 158, 11, 0.15)', borderWidth: 1, borderColor: 'rgba(245, 158, 11, 0.4)' },
    btnAbsent: { backgroundColor: 'rgba(239, 68, 68, 0.15)', borderWidth: 1, borderColor: 'rgba(239, 68, 68, 0.4)' },
    btnStartTrip: { backgroundColor: '#8B5CF6', marginTop: 20, paddingHorizontal: 24, paddingVertical: 12, borderRadius: 12, flex: 0 },
    btnText: { color: '#FFF', fontSize: 13, fontWeight: '800' },
    
    // EMPTY STATES
    emptyContainer: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 20 },
    emptyText: { color: '#94A3B8', fontSize: 14, textAlign: 'center', fontWeight: '500' },
    emptyBigContainer: { marginHorizontal: 20, alignItems: 'center', justifyContent: 'center', padding: 30, backgroundColor: 'rgba(15, 23, 42, 0.6)', borderRadius: 24, borderWidth: 1, borderColor: 'rgba(255,255,255,0.05)', marginTop: 20, marginBottom: 20 },
    emptyIconCircle: { width: 80, height: 80, borderRadius: 40, backgroundColor: 'rgba(255,255,255,0.05)', alignItems: 'center', justifyContent: 'center', marginBottom: 16 },
    emptyBigTitle: { color: '#FFF', fontSize: 18, fontWeight: '900', marginBottom: 8 },
    emptyBigText: { color: '#94A3B8', fontSize: 14, textAlign: 'center', fontWeight: '500', lineHeight: 22 }
});

export default PilotCellDriverScreen;
