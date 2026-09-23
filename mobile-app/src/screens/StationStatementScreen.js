import React, { useState, useCallback } from 'react';
import { View, Text, StyleSheet, FlatList, TouchableOpacity, ActivityIndicator, ScrollView, Platform } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import Icon from '@expo/vector-icons/MaterialCommunityIcons';
import { useFocusEffect } from '@react-navigation/native';
import DateTimePicker from '@react-native-community/datetimepicker';
import api from '../api/axios';

export default function StationStatementScreen({ route, navigation }) {
    const { stationId, stationName } = route.params;
    
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    
    const [data, setData] = useState({ summary: {}, fuels: [], payments: [] });
    
    const [startDate, setStartDate] = useState(null);
    const [endDate, setEndDate] = useState(null);
    const [showStartPicker, setShowStartPicker] = useState(false);
    const [showEndPicker, setShowEndPicker] = useState(false);

    const fetchData = async (hideLoader = false) => {
        if (!hideLoader) setLoading(true);
        try {
            let url = `/v1/fuel-stations/${stationId}/statement`;
            const params = [];
            if (startDate) params.push(`start_date=${startDate.toISOString().split('T')[0]}`);
            if (endDate) params.push(`end_date=${endDate.toISOString().split('T')[0]}`);
            if (params.length > 0) url += `?${params.join('&')}`;

            const res = await api.get(url);
            setData(res.data.data);
        } catch (e) {
            console.log('Station Statement Error:', e.response?.data || e.message);
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    };

    useFocusEffect(useCallback(() => { fetchData(true); }, []));

    const handleFilter = () => fetchData();
    
    const handleClear = () => {
        setStartDate(null);
        setEndDate(null);
        // We will fetch inside a setTimeout to ensure state is cleared
        setTimeout(() => fetchData(), 50);
    };

    const formatCurrency = (val) => new Intl.NumberFormat('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(val || 0);
    const formatDate = (dateString) => {
        if (!dateString) return '-';
        const d = new Date(dateString);
        return `${d.getDate().toString().padStart(2, '0')}.${(d.getMonth() + 1).toString().padStart(2, '0')}.${d.getFullYear()}`;
    };

    const isDebt = parseFloat(data.summary?.current_debt || 0) > 0;
    const debtColor = isDebt ? '#EF4444' : '#10B981';

    const renderHeader = () => (
        <View style={s.contentHeader}>
            <View style={s.filterCard}>
                <View style={s.filterRow}>
                    <TouchableOpacity style={s.dateBtn} onPress={() => setShowStartPicker(true)}>
                        <Text style={[s.dateText, !startDate && s.datePlaceholder]}>
                            {startDate ? formatDate(startDate) : 'Başlangıç'}
                        </Text>
                        <Icon name="calendar" size={18} color="#64748B" />
                    </TouchableOpacity>
                    <TouchableOpacity style={s.dateBtn} onPress={() => setShowEndPicker(true)}>
                        <Text style={[s.dateText, !endDate && s.datePlaceholder]}>
                            {endDate ? formatDate(endDate) : 'Bitiş'}
                        </Text>
                        <Icon name="calendar" size={18} color="#64748B" />
                    </TouchableOpacity>
                </View>

                {showStartPicker && (
                    <DateTimePicker
                        value={startDate || new Date()}
                        mode="date"
                        display="default"
                        onChange={(e, date) => {
                            setShowStartPicker(Platform.OS === 'ios');
                            if (date) setStartDate(date);
                        }}
                    />
                )}
                {showEndPicker && (
                    <DateTimePicker
                        value={endDate || new Date()}
                        mode="date"
                        display="default"
                        onChange={(e, date) => {
                            setShowEndPicker(Platform.OS === 'ios');
                            if (date) setEndDate(date);
                        }}
                    />
                )}

                <View style={s.filterActions}>
                    <TouchableOpacity style={s.clearBtn} onPress={handleClear}>
                        <Text style={s.clearBtnText}>Temizle</Text>
                    </TouchableOpacity>
                    <TouchableOpacity style={s.filterBtn} onPress={handleFilter}>
                        <Text style={s.filterBtnText}>Filtrele</Text>
                    </TouchableOpacity>
                </View>
            </View>

            <View style={s.kpiGrid}>
                <View style={s.kpiBox}><Text style={s.kpiLabel}>TOPLAM LİTRE</Text><Text style={s.kpiVal}>{parseFloat(data.summary?.total_liters || 0).toFixed(2)}</Text></View>
                <View style={s.kpiBox}><Text style={s.kpiLabel}>YAKIT TUTARI</Text><Text style={s.kpiVal}>{formatCurrency(data.summary?.total_fuel_cost)} ₺</Text></View>
                <View style={[s.kpiBox, { backgroundColor: '#ECFDF5', borderColor: '#A7F3D0' }]}><Text style={s.kpiLabel}>TOPLAM ÖDEME</Text><Text style={[s.kpiVal, { color: '#10B981' }]}>{formatCurrency(data.summary?.total_paid)} ₺</Text></View>
                <View style={[s.kpiBox, { backgroundColor: isDebt ? '#FEF2F2' : '#ECFDF5', borderColor: isDebt ? '#FECACA' : '#A7F3D0' }]}>
                    <Text style={s.kpiLabel}>CARİ BAKİYE</Text>
                    <Text style={[s.kpiVal, { color: debtColor }]}>{formatCurrency(Math.abs(data.summary?.current_debt || 0))} ₺</Text>
                </View>
            </View>

            <View style={s.sectionHeader}>
                <Text style={s.sectionTitle}>Yakıt Fişleri</Text>
                <View style={s.tableHeaderRow}>
                    <Text style={[s.th, { flex: 2 }]}>TARİH</Text>
                    <Text style={[s.th, { flex: 2 }]}>ARAÇ</Text>
                    <Text style={[s.th, { flex: 1.5, textAlign: 'right' }]}>LİTRE</Text>
                    <Text style={[s.th, { flex: 2.5, textAlign: 'right' }]}>TUTAR</Text>
                </View>
            </View>
            
            {data.fuels.length === 0 && <Text style={s.emptyText}>Bu aralıkta yakıt fişi bulunamadı.</Text>}
            {data.fuels.map((item, idx) => (
                <View key={`fuel_${item.id}_${idx}`} style={s.tableRow}>
                    <Text style={[s.td, { flex: 2 }]}>{formatDate(item.date)}</Text>
                    <Text style={[s.td, { flex: 2, fontWeight: '700' }]}>{item.vehicle?.plate}</Text>
                    <Text style={[s.td, { flex: 1.5, textAlign: 'right' }]}>{parseFloat(item.liters).toFixed(2)}</Text>
                    <Text style={[s.td, { flex: 2.5, textAlign: 'right', fontWeight: '800' }]}>{formatCurrency(item.total_cost)} ₺</Text>
                </View>
            ))}

            <View style={[s.sectionHeader, { marginTop: 24 }]}>
                <Text style={s.sectionTitle}>Ödemeler</Text>
                <View style={s.tableHeaderRow}>
                    <Text style={[s.th, { flex: 2 }]}>TARİH</Text>
                    <Text style={[s.th, { flex: 2 }]}>YÖNTEM</Text>
                    <Text style={[s.th, { flex: 2 }]}>NOT</Text>
                    <Text style={[s.th, { flex: 2, textAlign: 'right' }]}>TUTAR</Text>
                </View>
            </View>
            
            {data.payments.length === 0 && <Text style={s.emptyText}>Bu aralıkta ödeme bulunamadı.</Text>}
            {data.payments.map((item, idx) => (
                <View key={`payment_${item.id}_${idx}`} style={s.tableRow}>
                    <Text style={[s.td, { flex: 2 }]}>{formatDate(item.payment_date)}</Text>
                    <Text style={[s.td, { flex: 2, textTransform: 'capitalize' }]}>{item.payment_method}</Text>
                    <Text style={[s.td, { flex: 2, color: '#64748B' }]} numberOfLines={1}>{item.notes || '-'}</Text>
                    <Text style={[s.td, { flex: 2, textAlign: 'right', fontWeight: '800', color: '#10B981' }]}>{formatCurrency(item.amount)} ₺</Text>
                </View>
            ))}
            
            <View style={{ height: 40 }} />
        </View>
    );

    return (
        <SafeAreaView style={s.container} edges={['top']}>
            <View style={s.header}>
                <TouchableOpacity onPress={() => navigation.goBack()} style={s.backBtn}>
                    <Icon name="chevron-left" size={26} color="#0F172A" />
                </TouchableOpacity>
                <View style={{ flex: 1, paddingHorizontal: 12 }}>
                    <Text style={s.headerTitle} numberOfLines={1}>{stationName}</Text>
                    <Text style={s.headerSubtitle}>İstasyon Ekstresi</Text>
                </View>
                <View style={{width: 40}} />
            </View>

            {loading && !refreshing ? (
                <ActivityIndicator size="large" color="#3B82F6" style={{ marginTop: 100 }} />
            ) : (
                <ScrollView 
                    contentContainerStyle={s.scrollContent}
                    showsVerticalScrollIndicator={false}
                >
                    {renderHeader()}
                </ScrollView>
            )}
        </SafeAreaView>
    );
}

const s = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#F8FAFC' },
    header: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: 16, paddingTop: 10, paddingBottom: 15, backgroundColor: '#FFF', borderBottomWidth: 1, borderBottomColor: '#E2E8F0' },
    backBtn: { width: 40, height: 40, borderRadius: 20, backgroundColor: '#F1F5F9', alignItems: 'center', justifyContent: 'center' },
    headerTitle: { fontSize: 16, fontWeight: '900', color: '#0F172A' },
    headerSubtitle: { fontSize: 12, color: '#64748B', fontWeight: '600' },
    
    scrollContent: { padding: 16, paddingBottom: 80 },

    filterCard: { backgroundColor: '#FFF', padding: 16, borderRadius: 16, marginBottom: 16, borderWidth: 1, borderColor: '#E2E8F0' },
    filterRow: { flexDirection: 'row', gap: 12, marginBottom: 12 },
    dateBtn: { flex: 1, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 12, paddingVertical: 10, borderWidth: 1, borderColor: '#CBD5E1', borderRadius: 8 },
    dateText: { fontSize: 13, color: '#0F172A', fontWeight: '600' },
    datePlaceholder: { color: '#94A3B8' },
    filterActions: { flexDirection: 'row', justifyContent: 'flex-end', gap: 12 },
    clearBtn: { paddingHorizontal: 16, paddingVertical: 8, borderRadius: 8, borderWidth: 1, borderColor: '#E2E8F0' },
    clearBtnText: { fontSize: 13, color: '#64748B', fontWeight: '700' },
    filterBtn: { paddingHorizontal: 20, paddingVertical: 8, borderRadius: 8, backgroundColor: '#3B82F6' },
    filterBtnText: { fontSize: 13, color: '#FFF', fontWeight: '700' },

    kpiGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginBottom: 24 },
    kpiBox: { flexBasis: '48%', backgroundColor: '#FFF', padding: 12, borderRadius: 12, borderWidth: 1, borderColor: '#E2E8F0' },
    kpiLabel: { fontSize: 10, color: '#64748B', fontWeight: '700', letterSpacing: 0.5, marginBottom: 4 },
    kpiVal: { fontSize: 16, color: '#0F172A', fontWeight: '800' },

    sectionHeader: { marginBottom: 8 },
    sectionTitle: { fontSize: 16, fontWeight: '800', color: '#0F172A', marginBottom: 12 },
    tableHeaderRow: { flexDirection: 'row', paddingHorizontal: 8, paddingBottom: 8, borderBottomWidth: 1, borderBottomColor: '#E2E8F0' },
    th: { fontSize: 11, color: '#64748B', fontWeight: '700' },
    
    tableRow: { flexDirection: 'row', paddingHorizontal: 8, paddingVertical: 12, borderBottomWidth: 1, borderBottomColor: '#F1F5F9', backgroundColor: '#FFF', alignItems: 'center' },
    td: { fontSize: 12, color: '#334155' },
    
    emptyText: { padding: 16, textAlign: 'center', color: '#94A3B8', fontSize: 13, fontWeight: '500', backgroundColor: '#FFF', borderRadius: 8, marginTop: 8 }
});
