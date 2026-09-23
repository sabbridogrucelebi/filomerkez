import React, { useState, useEffect, useContext } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, FlatList, Alert, ActivityIndicator, Platform, Modal, TextInput, KeyboardAvoidingView, TouchableWithoutFeedback, Keyboard } from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import api from '../api/axios';
import { AuthContext } from '../context/AuthContext';
import MaterialCommunityIcons from '@expo/vector-icons/MaterialCommunityIcons';
import SpaceWaves from '../components/SpaceWaves';

export default function PilotCellRadiusScreen({ navigation }) {
    const insets = useSafeAreaInsets();
    const { userInfo } = useContext(AuthContext);
    const [isLoading, setIsLoading] = useState(true);
    const [students, setStudents] = useState([]);
    const [routeInfo, setRouteInfo] = useState(null);

    const [bulkModalVisible, setBulkModalVisible] = useState(false);
    const [bulkMorningRadius, setBulkMorningRadius] = useState('');
    const [bulkEveningRadius, setBulkEveningRadius] = useState('');
    const [isSavingBulk, setIsSavingBulk] = useState(false);

    useEffect(() => {
        const unsubscribe = navigation.addListener('focus', () => {
            fetchStudents();
        });
        return unsubscribe;
    }, [navigation]);

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

    const openRadiusMap = (student, type) => {
        // Check if student has that specific location set
        if (type === 'morning' && (!student.morning_lat || !student.morning_lng)) {
            Alert.alert("Konum Eksik", "Önce sabah noktasını belirlemelisiniz.");
            return;
        }
        if (type === 'evening' && (!student.evening_lat || !student.evening_lng)) {
            Alert.alert("Konum Eksik", "Önce akşam noktasını belirlemelisiniz.");
            return;
        }

        navigation.navigate('PilotCellRadiusMap', { student, type });
    };

    const handleBulkSave = async () => {
        if (!routeInfo) return;
        const morning = parseInt(bulkMorningRadius);
        const evening = parseInt(bulkEveningRadius);

        if (!morning && !evening) {
            Alert.alert("Hata", "Lütfen en az bir çap değeri girin.");
            return;
        }
        if ((morning && (morning < 10 || morning > 5000)) || (evening && (evening < 10 || evening > 5000))) {
            Alert.alert("Geçersiz Çap", "Çap değeri 10 metre ile 5000 metre arasında olmalıdır.");
            return;
        }

        setIsSavingBulk(true);
        try {
            const payload = { route_id: routeInfo.id };
            if (morning) payload.morning_radius = morning;
            if (evening) payload.evening_radius = evening;

            const res = await api.post('/v1/pilotcell/personnel/set-bulk-radius', payload);
            if (res.data?.success) {
                Alert.alert("Başarılı", "Tüm öğrencilerin çap değerleri güncellendi.");
                setBulkModalVisible(false);
                setBulkMorningRadius('');
                setBulkEveningRadius('');
                fetchStudents();
            }
        } catch (error) {
            Alert.alert("Hata", "Toplu çap güncellenirken bir hata oluştu.");
        } finally {
            setIsSavingBulk(false);
        }
    };

    const renderItem = ({ item }) => {
        const hasMorning = item.morning_lat && item.morning_lng;
        const hasEvening = item.evening_lat && item.evening_lng;

        return (
            <View style={styles.card}>
                <View style={styles.cardInfo}>
                    <Text style={styles.studentName}>{item.name}</Text>
                    <Text style={styles.studentSub}>Sınıf: {item.grade || 'Belirtilmemiş'}</Text>
                    
                    <View style={styles.parentRow}>
                        <MaterialCommunityIcons name="account-tie" size={16} color="#94A3B8" />
                        <Text style={styles.parentText} numberOfLines={1} adjustsFontSizeToFit>{item.parent1_name || 'Veli Yok'}</Text>
                    </View>
                    
                    <View style={styles.statusRow}>
                        {hasMorning ? (
                            <View style={styles.badgeSuccess}><MaterialCommunityIcons name="check-circle" size={12} color="#34D399" /><Text style={styles.badgeText}>Sabah {item.pickup_radius || 1000}m</Text></View>
                        ) : (
                            <View style={styles.badgePending}><MaterialCommunityIcons name="alert-circle" size={12} color="#FBBF24" /><Text style={styles.badgeTextWarning}>Sabah Yok</Text></View>
                        )}
                        {hasEvening ? (
                            <View style={styles.badgeSuccess}><MaterialCommunityIcons name="check-circle" size={12} color="#34D399" /><Text style={styles.badgeText}>Akşam {item.dropoff_radius || 1000}m</Text></View>
                        ) : (
                            <View style={styles.badgePending}><MaterialCommunityIcons name="alert-circle" size={12} color="#FBBF24" /><Text style={styles.badgeTextWarning}>Akşam Yok</Text></View>
                        )}
                    </View>
                </View>

                <View style={styles.actionWrap}>
                    <TouchableOpacity style={[styles.btn, styles.btnMorning]} onPress={() => openRadiusMap(item, 'morning')}>
                        <MaterialCommunityIcons name="radar" size={16} color="#FFF" />
                        <Text style={styles.btnText}>Sabah Çap</Text>
                    </TouchableOpacity>

                    <TouchableOpacity style={[styles.btn, styles.btnEvening]} onPress={() => openRadiusMap(item, 'evening')}>
                        <MaterialCommunityIcons name="radar" size={16} color="#FFF" />
                        <Text style={styles.btnText}>Akşam Çap</Text>
                    </TouchableOpacity>
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
                <Text style={styles.headerTitle}>Mesafe Belirleme</Text>
                <View style={{ width: 42 }} />
            </View>

            {students.length > 0 && (
                <View style={{ paddingHorizontal: 20, marginBottom: 10 }}>
                    <TouchableOpacity style={styles.bulkBtn} onPress={() => setBulkModalVisible(true)}>
                        <MaterialCommunityIcons name="radar" size={20} color="#FFF" style={{marginRight: 8}} />
                        <Text style={styles.bulkBtnText}>Tüm Öğrenciler İçin Toplu Çap Belirle</Text>
                    </TouchableOpacity>
                </View>
            )}

            {/* Bulk Modal */}
            <Modal visible={bulkModalVisible} animationType="slide" transparent={true}>
                <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : 'height'} style={{flex: 1}}>
                    <TouchableWithoutFeedback onPress={Keyboard.dismiss}>
                        <View style={styles.modalOverlay}>
                            <TouchableWithoutFeedback onPress={() => {}}>
                                <View style={[styles.modalContent, { paddingBottom: insets.bottom + 24 }]}>
                                    <View style={styles.modalHeader}>
                                        <Text style={styles.modalTitle}>Toplu Çap Belirle</Text>
                                        <TouchableOpacity onPress={() => setBulkModalVisible(false)} style={styles.closeBtn}>
                                            <MaterialCommunityIcons name="close" size={24} color="#94A3B8" />
                                        </TouchableOpacity>
                                    </View>
                                    
                                    <Text style={styles.modalDesc}>Belirlediğiniz değerler bu güzergahtaki tüm öğrencilere uygulanır.</Text>
                                    
                                    <View style={styles.inputGroup}>
                                        <Text style={styles.inputLabel}>Sabah Çapı (Metre)</Text>
                                        <View style={styles.inputWrapper}>
                                            <MaterialCommunityIcons name="weather-sunny" size={20} color="#0284C7" style={styles.inputIcon} />
                                            <TextInput
                                                style={styles.input}
                                                value={bulkMorningRadius}
                                                onChangeText={setBulkMorningRadius}
                                                keyboardType="numeric"
                                                placeholder="Örn: 1000"
                                                placeholderTextColor="#475569"
                                                maxLength={5}
                                            />
                                            <Text style={styles.unitText}>m</Text>
                                        </View>
                                    </View>

                                    <View style={styles.inputGroup}>
                                        <Text style={styles.inputLabel}>Akşam Çapı (Metre)</Text>
                                        <View style={styles.inputWrapper}>
                                            <MaterialCommunityIcons name="weather-night" size={20} color="#7C3AED" style={styles.inputIcon} />
                                            <TextInput
                                                style={styles.input}
                                                value={bulkEveningRadius}
                                                onChangeText={setBulkEveningRadius}
                                                keyboardType="numeric"
                                                placeholder="Örn: 1000"
                                                placeholderTextColor="#475569"
                                                maxLength={5}
                                            />
                                            <Text style={styles.unitText}>m</Text>
                                        </View>
                                    </View>

                                    <TouchableOpacity style={styles.saveBulkBtn} onPress={handleBulkSave} disabled={isSavingBulk}>
                                        {isSavingBulk ? <ActivityIndicator color="#FFF" /> : (
                                            <Text style={styles.saveBulkBtnText}>Tümüne Uygula ve Kaydet</Text>
                                        )}
                                    </TouchableOpacity>
                                </View>
                            </TouchableWithoutFeedback>
                        </View>
                    </TouchableWithoutFeedback>
                </KeyboardAvoidingView>
            </Modal>

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
    
    actionWrap: { width: 100, justifyContent: 'center', gap: 8 },
    btn: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', paddingVertical: 8, borderRadius: 10 },
    btnMorning: { backgroundColor: '#0284C7' }, 
    btnEvening: { backgroundColor: '#7C3AED' }, 
    btnText: { color: '#FFF', fontSize: 11, fontWeight: '800', marginLeft: 4 },
    
    emptyWrap: { alignItems: 'center', justifyContent: 'center', padding: 40, marginTop: 40, backgroundColor: 'rgba(15, 23, 42, 0.6)', borderRadius: 24, borderWidth: 1, borderColor: 'rgba(255,255,255,0.05)' },
    emptyText: { color: '#94A3B8', marginTop: 16, textAlign: 'center', fontSize: 14, fontWeight: '500', lineHeight: 22 },

    bulkBtn: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', backgroundColor: 'rgba(245, 158, 11, 0.2)', paddingVertical: 12, borderRadius: 16, borderWidth: 1, borderColor: 'rgba(245, 158, 11, 0.5)' },
    bulkBtnText: { color: '#FBBF24', fontSize: 14, fontWeight: '800' },

    modalOverlay: { flex: 1, backgroundColor: 'rgba(2, 6, 23, 0.8)', justifyContent: 'flex-end' },
    modalContent: { backgroundColor: '#0F172A', borderTopLeftRadius: 30, borderTopRightRadius: 30, padding: 24, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)' },
    modalHeader: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginBottom: 12 },
    modalTitle: { fontSize: 20, fontWeight: '900', color: '#FFF' },
    closeBtn: { padding: 4 },
    modalDesc: { color: '#94A3B8', fontSize: 13, marginBottom: 24, lineHeight: 18 },
    inputGroup: { marginBottom: 16 },
    inputLabel: { color: '#E2E8F0', fontSize: 14, fontWeight: '700', marginBottom: 8 },
    inputWrapper: { flexDirection: 'row', alignItems: 'center', backgroundColor: 'rgba(30, 41, 59, 0.8)', borderRadius: 12, borderWidth: 1, borderColor: 'rgba(139, 92, 246, 0.5)', paddingHorizontal: 16, height: 50 },
    inputIcon: { marginRight: 10 },
    input: { flex: 1, color: '#FFF', fontSize: 16, fontWeight: 'bold' },
    unitText: { color: '#8B5CF6', fontSize: 16, fontWeight: '900' },
    saveBulkBtn: { backgroundColor: '#8B5CF6', paddingVertical: 16, borderRadius: 16, alignItems: 'center', marginTop: 10, shadowColor: '#8B5CF6', shadowOffset: { width: 0, height: 4 }, shadowOpacity: 0.4, shadowRadius: 8, elevation: 5 },
    saveBulkBtnText: { color: '#FFF', fontSize: 16, fontWeight: '900', letterSpacing: 0.5 }
});
