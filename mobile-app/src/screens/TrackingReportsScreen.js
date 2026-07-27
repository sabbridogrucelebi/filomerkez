import React, { useState, useEffect, useContext } from 'react';
import { View, Text, StyleSheet, FlatList, ActivityIndicator, TouchableOpacity, Platform } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { MaterialCommunityIcons as Icon } from '@expo/vector-icons';
import api from '../api/axios';
import { AuthContext } from '../context/AuthContext';
import { Header, EmptyState } from '../components';
import DatePickerInput from '../components/DatePickerInput';

export default function TrackingReportsScreen({ navigation }) {
    const { hasPermission } = useContext(AuthContext);
    const [reports, setReports] = useState([]);
    const [loading, setLoading] = useState(true);
    const [date, setDate] = useState(new Date().toISOString().split('T')[0]);

    useEffect(() => {
        fetchReports();
    }, [date]);

    const fetchReports = async () => {
        if (!hasPermission('vehicles.view')) return;
        setLoading(true);
        try {
            const res = await api.get(`/v1/vehicle-tracking/reports/daily-work?date=${date}`);
            if (res.data && res.data.reports) {
                setReports(res.data.reports);
            }
        } catch (e) {
            console.error("Raporlar çekilemedi", e);
        } finally {
            setLoading(false);
        }
    };

    const renderItem = ({ item }) => {
        const isMissing = item.DateTime === '-';
        return (
            <View style={st.card}>
                <View style={st.cardTop}>
                    <View style={st.iconBox}>
                        <Icon name={isMissing ? "car-off" : "car-key"} size={22} color={isMissing ? "#64748B" : "#3B82F6"} />
                    </View>
                    <View style={st.cardInfo}>
                        <Text style={st.plateText}>{item.LicensePlate}</Text>
                        <Text style={st.driverText}>{item.Driver}</Text>
                    </View>
                    <View style={[st.badge, { backgroundColor: isMissing ? '#F1F5F9' : '#EFF6FF' }]}>
                        <Text style={[st.badgeText, { color: isMissing ? '#64748B' : '#3B82F6' }]}>{isMissing ? 'Veri Yok' : 'Kontak'}</Text>
                    </View>
                </View>

                <View style={st.cardDetails}>
                    <View style={st.detailRow}>
                        <Icon name="clock-outline" size={14} color="#64748B" />
                        <Text style={st.detailText}>{isMissing ? 'Kontak açılmadı' : item.DateTime}</Text>
                    </View>
                    <View style={st.detailRow}>
                        <Icon name="map-marker-outline" size={14} color="#64748B" />
                        <Text style={st.detailText} numberOfLines={2}>{item.Address}</Text>
                    </View>
                </View>
            </View>
        );
    };

    return (
        <View style={st.container}>
            <View style={{ backgroundColor: '#fff', zIndex: 10, paddingTop: Platform.OS === 'android' ? 44 : 54, paddingBottom: 12 }}>
                <View style={st.header}>
                    <TouchableOpacity onPress={() => navigation.goBack()} style={st.backBtn}>
                        <Icon name="chevron-left" size={26} color="#0F172A" />
                    </TouchableOpacity>
                    <View style={st.headerCenter}>
                        <Text style={st.headerTitle}>İlk Kontak Raporu</Text>
                        <Text style={st.headerSubtitle}>Günlük Çalışma Başlangıcı</Text>
                    </View>
                    <View style={{width: 40}} />
                </View>
                <View style={{ paddingHorizontal: 16, marginTop: 16 }}>
                    <Text style={st.label}>RAPOR TARİHİ</Text>
                    <DatePickerInput value={date} onChange={(d) => setDate(d)} />
                </View>
            </View>

            {loading ? (
                <View style={st.loader}><ActivityIndicator size="large" color="#3B82F6" /></View>
            ) : (
                <FlatList
                    data={reports}
                    renderItem={renderItem}
                    keyExtractor={(item, index) => index.toString()}
                    contentContainerStyle={st.listContent}
                    showsVerticalScrollIndicator={false}
                    ListEmptyComponent={<EmptyState title="Veri Bulunamadı" message="Seçili tarih için rapor verisi yok." icon="file-document-outline" />}
                />
            )}
        </View>
    );
}

const st = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#F8FAFC' },
    loader: { flex: 1, justifyContent: 'center', alignItems: 'center' },
    
    header: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 16 },
    backBtn: { width: 40, height: 40, borderRadius: 20, backgroundColor: '#F8FAFC', alignItems: 'center', justifyContent: 'center' },
    headerCenter: { flex: 1, alignItems: 'center' },
    headerTitle: { fontSize: 18, fontWeight: '800', color: '#0F172A' },
    headerSubtitle: { fontSize: 12, fontWeight: '600', color: '#64748B' },
    
    label: { fontSize: 11, fontWeight: '800', color: '#64748B', marginBottom: 8, marginLeft: 4, letterSpacing: 0.5 },
    listContent: { padding: 16, paddingBottom: 40 },
    
    card: { backgroundColor: '#fff', borderRadius: 20, padding: 16, marginBottom: 12, shadowColor: '#94A3B8', shadowOffset: { width: 0, height: 4 }, shadowOpacity: 0.1, shadowRadius: 8, elevation: 3 },
    cardTop: { flexDirection: 'row', alignItems: 'center', marginBottom: 12 },
    iconBox: { width: 44, height: 44, borderRadius: 12, backgroundColor: '#F1F5F9', alignItems: 'center', justifyContent: 'center' },
    cardInfo: { flex: 1, paddingHorizontal: 12 },
    plateText: { fontSize: 15, fontWeight: '800', color: '#0F172A', marginBottom: 2 },
    driverText: { fontSize: 12, color: '#64748B', fontWeight: '500' },
    
    badge: { paddingHorizontal: 10, paddingVertical: 4, borderRadius: 8 },
    badgeText: { fontSize: 11, fontWeight: '800' },
    
    cardDetails: { backgroundColor: '#F8FAFC', borderRadius: 12, padding: 12, gap: 8 },
    detailRow: { flexDirection: 'row', alignItems: 'flex-start', gap: 6 },
    detailText: { flex: 1, fontSize: 13, color: '#334155', fontWeight: '500', lineHeight: 18 }
});
