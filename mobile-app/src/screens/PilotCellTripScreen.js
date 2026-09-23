import React, { useState, useEffect, useContext, useRef, useCallback } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, FlatList, Alert, ActivityIndicator, Platform } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import MaterialCommunityIcons from '@expo/vector-icons/MaterialCommunityIcons';
import * as Location from 'expo-location';
import { AuthContext } from '../context/AuthContext';
import SpaceWaves from '../components/SpaceWaves';
import api from '../api/axios';

export default function PilotCellTripScreen({ route, navigation }) {
    const { userInfo } = useContext(AuthContext);
    const { route: routeData, direction } = route.params;
    const allStudentsRef = useRef(routeData?.students || []);
    const [activeStudents, setActiveStudents] = useState([]);
    const [absentStudents, setAbsentStudents] = useState([]);
    const [attendance, setAttendance] = useState({});
    const [isTripStarted, setIsTripStarted] = useState(false);
    const [activeTripId, setActiveTripId] = useState(null);
    const [isLoading, setIsLoading] = useState(false);
    const [lastSync, setLastSync] = useState(null);
    const locationIntervalRef = useRef(null);

    const isMorning = direction === 'morning';
    const directionLabel = isMorning ? 'Sabah' : 'Akşam';
    const themeColor = isMorning ? '#0284C7' : '#7C3AED';
    const themeColorLight = isMorning ? 'rgba(2, 132, 199, 0.2)' : 'rgba(124, 58, 237, 0.2)';

    // Sync absence status from API
    const syncAbsences = useCallback(async () => {
        if (!routeData?.id) return;
        try {
            const res = await api.get(`/v1/pilotcell/personnel/route-absences?route_id=${routeData.id}`);
            if (res.data?.success) {
                const absentIds = new Set(res.data.data.absent_student_ids || []);
                const all = allStudentsRef.current;
                setActiveStudents(all.filter(s => !absentIds.has(s.id)));
                setAbsentStudents(all.filter(s => absentIds.has(s.id)));
                setLastSync(new Date().toLocaleTimeString('tr-TR'));
            }
        } catch (e) {
            // Silent fail for polling
        }
    }, [routeData?.id]);

    // Initial sync + 5-second polling
    useEffect(() => {
        syncAbsences();
        const interval = setInterval(syncAbsences, 5000);
        return () => clearInterval(interval);
    }, [syncAbsences]);

    // Clean up location interval on unmount
    useEffect(() => {
        return () => {
            if (locationIntervalRef.current) {
                clearInterval(locationIntervalRef.current);
            }
        };
    }, []);

    const startLocationTracking = async (tripId) => {
        const { status } = await Location.requestForegroundPermissionsAsync();
        if (status !== 'granted') {
            Alert.alert('İzin Reddedildi', 'Canlı takip için konum izni gereklidir.');
            return;
        }

        // Send location every 3 seconds
        locationIntervalRef.current = setInterval(async () => {
            try {
                const location = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.High });
                await api.post('/v1/pilotcell/location/update', {
                    trip_id: tripId,
                    lat: location.coords.latitude,
                    lng: location.coords.longitude,
                    accuracy: location.coords.accuracy,
                    speed: location.coords.speed,
                    heading: location.coords.heading,
                    recorded_at: new Date().toISOString()
                });
            } catch (error) {
                console.log('Location update error:', error);
            }
        }, 3000);
    };

    const handleStartTrip = () => {
        Alert.alert(
            `${directionLabel} Servisi Başlat`,
            `${routeData?.name || 'Güzergah'} için ${directionLabel.toLowerCase()} servisini başlatmak istediğinize emin misiniz?`,
            [
                { text: 'İptal', style: 'cancel' },
                { text: 'Başlat', onPress: async () => {
                    setIsLoading(true);
                    try {
                        const res = await api.post('/v1/pilotcell/trips/start', {
                            route_id: routeData.id,
                            direction: direction
                        });

                        if (res.data?.success) {
                            const newTripId = res.data.data.id;
                            setActiveTripId(newTripId);
                            setIsTripStarted(true);
                            
                            const initialAttendance = {};
                            activeStudents.forEach(s => {
                                initialAttendance[s.id] = null;
                            });
                            setAttendance(initialAttendance);

                            startLocationTracking(newTripId);
                        }
                    } catch (error) {
                        Alert.alert('Hata', 'Sefer başlatılamadı.');
                    } finally {
                        setIsLoading(false);
                    }
                }}
            ]
        );
    };

    const handleEndTrip = () => {
        Alert.alert(
            'Servisi Bitir',
            'Bu seferi bitirmek istediğinize emin misiniz?',
            [
                { text: 'İptal', style: 'cancel' },
                { text: 'Bitir', style: 'destructive', onPress: async () => {
                    if (locationIntervalRef.current) {
                        clearInterval(locationIntervalRef.current);
                        locationIntervalRef.current = null;
                    }

                    if (activeTripId) {
                        try {
                            await api.post(`/v1/pilotcell/trips/${activeTripId}/end`);
                        } catch (error) {
                            console.log('Error ending trip', error);
                        }
                    }

                    setIsTripStarted(false);
                    setActiveTripId(null);
                    setAttendance({});
                    Alert.alert('Başarılı', 'Sefer tamamlandı.');
                    navigation.goBack();
                }}
            ]
        );
    };

    const [pendingAttendance, setPendingAttendance] = useState({});
    const timersRef = useRef({});

    useEffect(() => {
        return () => {
            Object.values(timersRef.current).forEach(t => {
                clearTimeout(t.timeoutId);
                clearInterval(t.intervalId);
            });
        };
    }, []);

    const handleAttendanceAction = (studentId, status) => {
        if (pendingAttendance[studentId]?.status === status) return;

        cancelPendingAttendance(studentId);

        const timeoutId = setTimeout(() => {
            commitAttendance(studentId, status);
        }, 10000);

        const intervalId = setInterval(() => {
            setPendingAttendance(prev => {
                const current = prev[studentId];
                if (!current || current.remaining <= 1) {
                    clearInterval(intervalId);
                    return prev;
                }
                return {
                    ...prev,
                    [studentId]: { ...current, remaining: current.remaining - 1 }
                };
            });
        }, 1000);

        timersRef.current[studentId] = { timeoutId, intervalId };

        setPendingAttendance(prev => ({
            ...prev,
            [studentId]: { status, remaining: 10 }
        }));
    };

    const cancelPendingAttendance = (studentId) => {
        if (timersRef.current[studentId]) {
            clearTimeout(timersRef.current[studentId].timeoutId);
            clearInterval(timersRef.current[studentId].intervalId);
            delete timersRef.current[studentId];
        }
        
        setPendingAttendance(prev => {
            const newPending = { ...prev };
            delete newPending[studentId];
            return newPending;
        });
    };

    const commitAttendance = async (studentId, status) => {
        setAttendance(prev => ({ ...prev, [studentId]: status }));
        
        if (timersRef.current[studentId]) {
            clearTimeout(timersRef.current[studentId].timeoutId);
            clearInterval(timersRef.current[studentId].intervalId);
            delete timersRef.current[studentId];
        }

        setPendingAttendance(prev => {
            const newPending = { ...prev };
            delete newPending[studentId];
            return newPending;
        });

        if (activeTripId) {
            try {
                await api.post('/v1/pilotcell/attendance/update', {
                    trip_id: activeTripId,
                    student_id: studentId,
                    status: status,
                    timestamp: new Date().toISOString()
                });
            } catch (error) {
                console.log('Attendance update error', error);
            }
        }
    };

    const getStatusColor = (status) => {
        switch(status) {
            case 'boarded': return '#10B981';
            case 'alighted': return '#3B82F6';
            case 'absent': return '#EF4444';
            default: return 'rgba(255,255,255,0.1)';
        }
    };

    const getStatusLabel = (status) => {
        switch(status) {
            case 'boarded': return 'Bindi ✓';
            case 'alighted': return 'İndi ✓';
            case 'absent': return 'Gelmedi ✗';
            default: return '';
        }
    };

    const renderStudentItem = ({ item }) => {
        const currentStatus = attendance[item.id];
        const hasLocation = isMorning 
            ? (item.morning_lat && item.morning_lng) 
            : (item.evening_lat && item.evening_lng);

        return (
            <View style={[styles.studentCard, currentStatus && { borderColor: getStatusColor(currentStatus), borderWidth: 1.5 }]}>
                <View style={styles.studentInfo}>
                    <Text style={styles.studentName}>{item.name}</Text>
                    <Text style={styles.studentGrade}>Sınıf: {item.grade || '-'}</Text>
                    {currentStatus && (
                        <View style={[styles.statusBadge, { backgroundColor: getStatusColor(currentStatus) + '30' }]}>
                            <Text style={[styles.statusBadgeText, { color: getStatusColor(currentStatus) }]}>{getStatusLabel(currentStatus)}</Text>
                        </View>
                    )}
                </View>

                {isTripStarted && pendingAttendance[item.id] ? (
                    <View style={styles.pendingContainer}>
                        <View style={styles.pendingTextWrap}>
                            <ActivityIndicator size="small" color="#F59E0B" style={{ marginRight: 8 }} />
                            <Text style={styles.pendingText}>
                                Bildirim Gönderiliyor ({pendingAttendance[item.id].remaining})
                            </Text>
                        </View>
                        <TouchableOpacity style={styles.cancelBtn} onPress={() => cancelPendingAttendance(item.id)}>
                            <MaterialCommunityIcons name="close" size={20} color="#FFF" />
                        </TouchableOpacity>
                    </View>
                ) : (
                    <>
                        {isTripStarted && !currentStatus && (
                            <View style={styles.attendanceBtns}>
                                <TouchableOpacity style={[styles.attBtn, { backgroundColor: '#10B981' }]} onPress={() => handleAttendanceAction(item.id, 'boarded')}>
                                    <MaterialCommunityIcons name="account-check" size={16} color="#FFF" />
                                    <Text style={styles.attBtnText}>Bindi</Text>
                                </TouchableOpacity>
                                <TouchableOpacity style={[styles.attBtn, { backgroundColor: '#EF4444' }]} onPress={() => handleAttendanceAction(item.id, 'absent')}>
                                    <MaterialCommunityIcons name="account-cancel" size={16} color="#FFF" />
                                    <Text style={styles.attBtnText}>Gelmedi</Text>
                                </TouchableOpacity>
                            </View>
                        )}

                        {isTripStarted && currentStatus === 'boarded' && (
                            <TouchableOpacity style={[styles.attBtn, { backgroundColor: '#3B82F6', marginTop: 12 }]} onPress={() => handleAttendanceAction(item.id, 'alighted')}>
                                <MaterialCommunityIcons name="account-arrow-down" size={16} color="#FFF" />
                                <Text style={styles.attBtnText}>İndi</Text>
                            </TouchableOpacity>
                        )}
                    </>
                )}
            </View>
        );
    };

    const boardedCount = Object.values(attendance).filter(v => v === 'boarded').length;
    const alightedCount = Object.values(attendance).filter(v => v === 'alighted').length;
    const absentCount = Object.values(attendance).filter(v => v === 'absent').length;

    return (
        <SafeAreaView style={styles.container}>
            <SpaceWaves />

            {/* Header */}
            <View style={styles.header}>
                <TouchableOpacity style={styles.backBtn} onPress={() => {
                    if (isTripStarted) {
                        Alert.alert('Dikkat', 'Sefer devam ederken çıkamazsınız. Önce seferi bitirin.');
                    } else {
                        navigation.goBack();
                    }
                }}>
                    <MaterialCommunityIcons name="arrow-left" size={26} color="#FFF" />
                </TouchableOpacity>
                <Text style={styles.headerTitle}>{directionLabel} Servisi</Text>
                <View style={{ width: 42 }} />
            </View>

            {/* Route Info Card */}
            <View style={[styles.routeCard, { borderColor: themeColor + '60' }]}>
                <View style={styles.routeCardRow}>
                    <View style={[styles.routeIconWrap, { backgroundColor: themeColorLight }]}>
                        <MaterialCommunityIcons name={isMorning ? "weather-sunny" : "weather-night"} size={28} color={themeColor} />
                    </View>
                    <View style={{ flex: 1 }}>
                        <Text style={styles.routeSchool}>{routeData?.customer?.company_name || 'Okul'}</Text>
                        <Text style={styles.routeName}>{routeData?.name || 'Güzergah'}</Text>
                        <Text style={[styles.routePlate, { color: themeColor }]}>{routeData?.vehicle?.plate || '-'}</Text>
                    </View>
                </View>

                {isTripStarted && (
                    <View style={styles.statsRow}>
                        <View style={styles.statItem}>
                            <Text style={[styles.statNum, { color: '#10B981' }]}>{boardedCount}</Text>
                            <Text style={styles.statLabel}>Bindi</Text>
                        </View>
                        <View style={styles.statItem}>
                            <Text style={[styles.statNum, { color: '#3B82F6' }]}>{alightedCount}</Text>
                            <Text style={styles.statLabel}>İndi</Text>
                        </View>
                        <View style={styles.statItem}>
                            <Text style={[styles.statNum, { color: '#EF4444' }]}>{absentCount}</Text>
                            <Text style={styles.statLabel}>Gelmedi</Text>
                        </View>
                        <View style={styles.statItem}>
                            <Text style={[styles.statNum, { color: '#FFF' }]}>{activeStudents.length}</Text>
                            <Text style={styles.statLabel}>Toplam</Text>
                        </View>
                    </View>
                )}
            </View>

            {/* Start / End Trip Button */}
            {!isTripStarted ? (
                <View style={{ paddingHorizontal: 20, marginBottom: 16 }}>
                    <TouchableOpacity style={[styles.startBtn, { backgroundColor: themeColor }]} onPress={handleStartTrip}>
                        <MaterialCommunityIcons name="play-circle" size={24} color="#FFF" style={{ marginRight: 8 }} />
                        <Text style={styles.startBtnText}>{directionLabel} Servisini Başlat</Text>
                    </TouchableOpacity>
                </View>
            ) : (
                <View style={{ paddingHorizontal: 20, marginBottom: 16 }}>
                    <TouchableOpacity style={[styles.startBtn, { backgroundColor: '#EF4444' }]} onPress={handleEndTrip}>
                        <MaterialCommunityIcons name="stop-circle" size={24} color="#FFF" style={{ marginRight: 8 }} />
                        <Text style={styles.startBtnText}>Servisi Bitir</Text>
                    </TouchableOpacity>
                </View>
            )}

            {/* Student List */}
            {lastSync && <Text style={styles.syncIndicator}>Son güncelleme: {lastSync} (5sn aralıklı)</Text>}
            <Text style={styles.listTitle}>Gelecek Öğrenciler ({activeStudents.length})</Text>
            <FlatList
                data={activeStudents}
                keyExtractor={item => item.id.toString()}
                renderItem={renderStudentItem}
                contentContainerStyle={styles.listContent}
                ListFooterComponent={
                    absentStudents.length > 0 ? (
                        <View style={styles.absentSection}>
                            <View style={styles.absentHeader}>
                                <MaterialCommunityIcons name="account-cancel" size={20} color="#EF4444" />
                                <Text style={styles.absentTitle}>Gelmeyecek ({absentStudents.length})</Text>
                            </View>
                            {absentStudents.map(s => (
                                <View key={s.id} style={styles.absentCard}>
                                    <Text style={styles.absentName}>{s.name}</Text>
                                    <View style={styles.absentBadge}>
                                        <Text style={styles.absentBadgeText}>Veli Bildirdi</Text>
                                    </View>
                                </View>
                            ))}
                        </View>
                    ) : null
                }
                ListEmptyComponent={
                    <View style={styles.emptyWrap}>
                        <MaterialCommunityIcons name="account-group-outline" size={48} color="#64748B" />
                        <Text style={styles.emptyText}>Bu güzergahta kayıtlı öğrenci yok.</Text>
                    </View>
                }
            />
        </SafeAreaView>
    );
}

const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#020617' },
    header: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 20, paddingTop: 10, paddingBottom: 15, zIndex: 10 },
    backBtn: { padding: 8, backgroundColor: 'rgba(255,255,255,0.1)', borderRadius: 12 },
    headerTitle: { fontSize: 20, fontWeight: '900', color: '#FFF', letterSpacing: 0.5 },

    routeCard: { marginHorizontal: 20, backgroundColor: 'rgba(30, 41, 59, 0.7)', borderRadius: 20, padding: 20, marginBottom: 16, borderWidth: 1 },
    routeCardRow: { flexDirection: 'row', alignItems: 'center' },
    routeIconWrap: { padding: 14, borderRadius: 16, marginRight: 16 },
    routeSchool: { color: '#94A3B8', fontSize: 13, fontWeight: '600' },
    routeName: { color: '#FFF', fontSize: 22, fontWeight: '800', marginVertical: 2 },
    routePlate: { fontSize: 14, fontWeight: '700' },

    statsRow: { flexDirection: 'row', justifyContent: 'space-around', marginTop: 16, paddingTop: 16, borderTopWidth: 1, borderTopColor: 'rgba(255,255,255,0.1)' },
    statItem: { alignItems: 'center' },
    statNum: { fontSize: 22, fontWeight: '900' },
    statLabel: { color: '#94A3B8', fontSize: 11, fontWeight: '600', marginTop: 2 },

    startBtn: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', paddingVertical: 16, borderRadius: 16, shadowColor: '#000', shadowOffset: { width: 0, height: 4 }, shadowopacity: 1, shadowRadius: 8, elevation: 5 },
    startBtnText: { color: '#FFF', fontSize: 17, fontWeight: '900', letterSpacing: 0.5 },

    listTitle: { color: '#FFF', fontSize: 16, fontWeight: '800', paddingHorizontal: 20, marginBottom: 12 },
    syncIndicator: { color: '#10B981', fontSize: 10, fontWeight: '600', paddingHorizontal: 20, marginBottom: 4, textAlign: 'right' },
    listContent: { paddingHorizontal: 20, paddingBottom: 30 },

    studentCard: { backgroundColor: 'rgba(30, 41, 59, 0.7)', borderRadius: 16, padding: 16, marginBottom: 12, flexDirection: 'row', alignItems: 'center', borderWidth: 1, borderColor: 'rgba(255,255,255,0.05)' },
    studentInfo: { flex: 1 },
    studentName: { color: '#FFF', fontSize: 15, fontWeight: '800' },
    studentGrade: { color: '#94A3B8', fontSize: 12, fontWeight: '500', marginTop: 2 },
    statusBadge: { alignSelf: 'flex-start', paddingHorizontal: 10, paddingVertical: 4, borderRadius: 8, marginTop: 6 },
    statusBadgeText: { fontSize: 11, fontWeight: '800' },

    attendanceBtns: { flexDirection: 'column', gap: 6 },
    attBtn: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: 12, paddingVertical: 8, borderRadius: 10 },
    attBtnText: { color: '#FFF', fontSize: 13, fontWeight: '700', marginLeft: 6 },

    pendingContainer: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', backgroundColor: 'rgba(245, 158, 11, 0.1)', padding: 12, borderRadius: 12, marginTop: 12, borderWidth: 1, borderColor: 'rgba(245, 158, 11, 0.3)' },
    pendingTextWrap: { flexDirection: 'row', alignItems: 'center', flex: 1 },
    pendingText: { color: '#F59E0B', fontSize: 14, fontWeight: '600' },
    cancelBtn: { backgroundColor: 'rgba(239, 68, 68, 0.2)', padding: 6, borderRadius: 8, borderWidth: 1, borderColor: 'rgba(239, 68, 68, 0.5)' },

    emptyWrap: { alignItems: 'center', padding: 40, marginTop: 20 },
    emptyText: { color: '#94A3B8', marginTop: 12, fontSize: 14, fontWeight: '500' },

    absentSection: { marginTop: 16, paddingTop: 16, borderTopWidth: 1, borderTopColor: 'rgba(255,255,255,0.08)' },
    absentHeader: { flexDirection: 'row', alignItems: 'center', gap: 8, marginBottom: 12 },
    absentTitle: { color: '#EF4444', fontSize: 15, fontWeight: '800' },
    absentCard: { backgroundColor: 'rgba(239, 68, 68, 0.08)', borderRadius: 12, padding: 14, marginBottom: 8, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', borderWidth: 1, borderColor: 'rgba(239, 68, 68, 0.2)' },
    absentName: { color: '#94A3B8', fontSize: 14, fontWeight: '700', textDecorationLine: 'line-through' },
    absentBadge: { backgroundColor: 'rgba(239, 68, 68, 0.2)', paddingHorizontal: 10, paddingVertical: 4, borderRadius: 8 },
    absentBadgeText: { color: '#EF4444', fontSize: 11, fontWeight: '800' },
});
