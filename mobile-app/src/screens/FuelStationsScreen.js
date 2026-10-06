import React, { useState, useCallback } from 'react';
import { View, Text, StyleSheet, FlatList, TouchableOpacity, ActivityIndicator } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import Icon from '@expo/vector-icons/MaterialCommunityIcons';
import { useFocusEffect } from '@react-navigation/native';
import api from '../api/axios';
import useStatusBarStyle from '../hooks/useStatusBarStyle';

export default function FuelStationsScreen({ navigation }) {
    useStatusBarStyle('dark-content');
    const [stations, setStations] = useState([]);
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    const [kpi, setKpi] = useState({ totalDebt: 0, totalPaid: 0 });

    const fetchData = async (hideLoader = false) => {
        if (!hideLoader) setLoading(true);
        try {
            const res = await api.get('/v1/fuel-stations');
            const data = res.data.data || [];
            
            // Sort by name
            data.sort((a, b) => a.name.localeCompare(b.name));
            setStations(data);
            
            const tDebt = data.reduce((sum, item) => sum + parseFloat(item.current_debt || 0), 0);
            const tPaid = data.reduce((sum, item) => sum + parseFloat(item.total_paid || 0), 0);
            setKpi({ totalDebt: tDebt, totalPaid: tPaid });

        } catch (e) {
            console.log('Fuel Stations Error:', e.response?.data || e.message);
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    };

    useFocusEffect(useCallback(() => { fetchData(true); }, []));

    const formatCurrency = (val) => {
        return new Intl.NumberFormat('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(val);
    };

    const renderStationRow = ({ item }) => {
        const isDebt = parseFloat(item.current_debt) > 0;
        const debtColor = isDebt ? '#EF4444' : '#10B981';
        
        return (
            <TouchableOpacity 
                style={s.dataRow} 
                activeOpacity={0.7}
                onPress={() => navigation.navigate('StationStatement', { stationId: item.id, stationName: item.name })}
            >
                <View style={s.rowTop}>
                    <Icon name="gas-station" size={20} color="#3B82F6" style={{ marginRight: 8 }} />
                    <Text style={s.colName} numberOfLines={1}>{item.name}</Text>
                    <View style={[s.badge, { backgroundColor: isDebt ? '#FEF2F2' : '#ECFDF5' }]}>
                        <Text style={[s.badgeText, { color: debtColor }]}>{isDebt ? 'Borçlu' : 'Alacaklı'}</Text>
                    </View>
                </View>
                <Text style={s.colLegalName} numberOfLines={1}>{item.legal_name || 'Ünvan Belirtilmedi'}</Text>
                
                <View style={s.rowDivider} />
                
                <View style={s.rowMid}>
                    <View style={s.dataCell}><Text style={s.cellLabel}>İskonto</Text><Text style={s.cellVal}>{item.discount_type === 'percentage' ? `%${item.discount_value}` : `${item.discount_value} ₺`}</Text></View>
                    <View style={s.dataCell}><Text style={s.cellLabel}>Toplam Litre</Text><Text style={s.cellVal}>{parseFloat(item.total_liters).toFixed(2)} L</Text></View>
                    <View style={s.dataCell}><Text style={s.cellLabel}>Brüt (KDV Hariç)</Text><Text style={s.cellVal}>₺{formatCurrency(item.gross_total)}</Text></View>
                    
                    <View style={s.dataCell}><Text style={s.cellLabel}>İskonto Top.</Text><Text style={s.cellVal}>₺{formatCurrency(item.discount_total)}</Text></View>
                    <View style={s.dataCell}><Text style={s.cellLabel}>Net Borç (KDV Dahil)</Text><Text style={s.cellVal}>₺{formatCurrency(item.net_debt)}</Text></View>
                    <View style={s.dataCell}><Text style={s.cellLabel}>Ödenen</Text><Text style={[s.cellVal, { color: '#10B981' }]}>₺{formatCurrency(item.total_paid)}</Text></View>
                </View>

                <View style={[s.rowDivider, { marginTop: 4 }]} />

                <View style={s.rowBot}>
                    <Text style={s.debtLabel}>ANLIK CARİ BORÇ</Text>
                    <View style={[s.debtBadge, { backgroundColor: isDebt ? '#FEF2F2' : '#ECFDF5' }]}>
                        <Text style={[s.debtVal, { color: debtColor }]}>
                            {parseFloat(item.current_debt) < 0 ? '-' : ''}₺{formatCurrency(Math.abs(item.current_debt))}
                        </Text>
                    </View>
                </View>
            </TouchableOpacity>
        );
    };

    if (loading && !refreshing) {
        return (
            <SafeAreaView style={s.container} edges={['top']}>
                <View style={s.header}>
                    <TouchableOpacity onPress={() => navigation.goBack()} style={s.backBtn}>
                        <Icon name="chevron-left" size={26} color="#0F172A" />
                    </TouchableOpacity>
                    <Text style={s.headerTitle}>Petrol İstasyonları</Text>
                    <View style={{width: 40}} />
                </View>
                <ActivityIndicator size="large" color="#3B82F6" style={{ marginTop: 100 }} />
            </SafeAreaView>
        );
    }

    return (
        <SafeAreaView style={s.container} edges={['top']}>
            <View style={s.header}>
                <TouchableOpacity onPress={() => navigation.goBack()} style={s.backBtn}>
                    <Icon name="chevron-left" size={26} color="#0F172A" />
                </TouchableOpacity>
                <Text style={s.headerTitle}>Petrol İstasyonları</Text>
                <View style={{width: 40}} />
            </View>

            {/* Top KPIs */}
            <View style={s.kpiGrid}>
                <View style={[s.kpiBox, { backgroundColor: '#EC4899', marginRight: 8 }]}>
                    <Text style={s.kpiLabel}>Tüm İstasyon Borçları</Text>
                    <Text style={s.kpiVal} numberOfLines={1}>₺{formatCurrency(kpi.totalDebt)}</Text>
                </View>
                <View style={[s.kpiBox, { backgroundColor: '#10B981', marginLeft: 8 }]}>
                    <Text style={s.kpiLabel}>Toplam Ödenen</Text>
                    <Text style={s.kpiVal} numberOfLines={1}>₺{formatCurrency(kpi.totalPaid)}</Text>
                </View>
            </View>

            <FlatList
                data={stations}
                keyExtractor={item => item.id.toString()}
                renderItem={renderStationRow}
                contentContainerStyle={s.listContent}
                showsVerticalScrollIndicator={false}
                refreshing={refreshing}
                onRefresh={() => { setRefreshing(true); fetchData(false); }}
                ListEmptyComponent={
                    <View style={s.empty}>
                        <Icon name="store-off" size={48} color="#CBD5E1" />
                        <Text style={s.emptyText}>Henüz petrol istasyonu eklenmemiş.</Text>
                    </View>
                }
            />
        </SafeAreaView>
    );
}

const s = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#F1F5F9' },
    header: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 16, paddingTop: 10, paddingBottom: 15 },
    backBtn: { width: 40, height: 40, borderRadius: 20, backgroundColor: '#FFF', alignItems: 'center', justifyContent: 'center', shadowColor: '#000', shadowOffset: { width: 0, height: 2 }, shadowOpacity: 0.05, shadowRadius: 4, elevation: 2 },
    headerTitle: { fontSize: 18, fontWeight: '900', color: '#0F172A' },
    
    kpiGrid: { flexDirection: 'row', paddingHorizontal: 16, paddingBottom: 16 },
    kpiBox: { flex: 1, padding: 16, borderRadius: 16, shadowColor: '#000', shadowOffset: { width: 0, height: 4 }, shadowOpacity: 0.15, shadowRadius: 8, elevation: 4 },
    kpiLabel: { fontSize: 11, color: 'rgba(255,255,255,0.9)', fontWeight: '700', textTransform: 'uppercase', marginBottom: 6 },
    kpiVal: { fontSize: 22, color: '#FFF', fontWeight: '900', letterSpacing: -0.5 },

    listContent: { paddingHorizontal: 16, paddingBottom: 100 },
    empty: { alignItems: 'center', marginTop: 40 },
    emptyText: { fontSize: 14, color: '#94A3B8', fontWeight: '600', marginTop: 12 },

    dataRow: { backgroundColor: '#FFF', borderRadius: 16, padding: 16, marginBottom: 16, shadowColor: '#000', shadowOffset: { width: 0, height: 2 }, shadowOpacity: 0.04, shadowRadius: 6, elevation: 2 },
    rowTop: { flexDirection: 'row', alignItems: 'center' },
    colName: { flex: 1, fontSize: 16, color: '#0F172A', fontWeight: '800' },
    badge: { paddingHorizontal: 8, paddingVertical: 4, borderRadius: 8 },
    badgeText: { fontSize: 10, fontWeight: '800', textTransform: 'uppercase' },
    
    colLegalName: { fontSize: 12, color: '#64748B', fontWeight: '500', marginTop: 4, paddingLeft: 28 },
    
    rowDivider: { height: 1, backgroundColor: '#F1F5F9', marginVertical: 12 },
    
    rowMid: { flexDirection: 'row', flexWrap: 'wrap', justifyContent: 'space-between' },
    dataCell: { width: '31%', marginBottom: 10 },
    cellLabel: { fontSize: 10, color: '#94A3B8', fontWeight: '600', textTransform: 'uppercase', marginBottom: 2 },
    cellVal: { fontSize: 13, color: '#334155', fontWeight: '700' },

    rowBot: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingTop: 8 },
    debtLabel: { fontSize: 12, color: '#0F172A', fontWeight: '800', letterSpacing: 1 },
    debtBadge: { paddingHorizontal: 12, paddingVertical: 6, borderRadius: 12 },
    debtVal: { fontSize: 16, fontWeight: '900' }
});
