import React, { useState, useEffect } from 'react';
import { View, Text, StyleSheet, ScrollView, ActivityIndicator, TouchableOpacity, RefreshControl } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { MaterialCommunityIcons as Icon } from '@expo/vector-icons';
import api from '../api/axios';
import { colors, spacing, radius } from '../theme';
import { Header, EmptyState } from '../components';

export default function FinanceScreen({ navigation }) {
    const [summary, setSummary] = useState(null);
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    const [error, setError] = useState(null);

    const fetchSummary = async (isRefresh = false) => {
        try {
            if (isRefresh) setRefreshing(true); else setLoading(true);
            setError(null);
            const response = await api.get('/v1/finance/summary');
            if (response.data.success) setSummary(response.data.data);
            else setError(response.data.message || 'Veri alınamadı.');
        } catch (err) {
            if (err.response?.status === 403) setError('Bu alanı görüntüleme yetkiniz yok.');
            else setError('Bağlantı hatası.');
        } finally {
            setLoading(false); setRefreshing(false);
        }
    };

    useEffect(() => { fetchSummary(); }, []);

    const formatCurrency = (amount) => {
        if (amount === null || amount === undefined) return '- (Yetki Yok)';
        return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' }).format(amount);
    };

    if (loading && !refreshing) {
        return (
            <View style={[s.container, { justifyContent: 'center', alignItems: 'center' }]}>
                <ActivityIndicator size="large" color="#000" />
            </View>
        );
    }

    if (error && !refreshing && !summary) {
        return (
            <View style={s.container}>
                <View style={{flex: 1, justifyContent: 'center', alignItems: 'center'}}>
                    <Text style={{color:'#000'}}>{error}</Text>
                    <TouchableOpacity onPress={() => fetchSummary()} style={s.retryBtn}>
                        <Text style={s.retryTxt}>Tekrar Dene</Text>
                    </TouchableOpacity>
                </View>
            </View>
        );
    }

    return (
        <View style={s.container}>
            <Header title="Finans Yönetimi" showBack={true} />
            <ScrollView contentContainerStyle={{ padding: 20 }}>
                {summary && (
                    <View style={s.summaryCard}>
                        <Icon name="chart-line" size={32} color="#10B981" />
                        <Text style={s.summaryTitle}>Toplam Gider (Bu Ay)</Text>
                        <Text style={s.summaryAmount}>{formatCurrency(summary.total_expenses)}</Text>
                    </View>
                )}

                <Text style={s.sectionTitle}>Finansal İşlemler</Text>
                
                <TouchableOpacity style={s.menuBtn} onPress={() => navigation.navigate('Expenses')}>
                    <View style={[s.menuIconBox, { backgroundColor: '#ECFDF5' }]}>
                        <Icon name="cash-multiple" size={24} color="#10B981" />
                    </View>
                    <View style={s.menuContent}>
                        <Text style={s.menuTitle}>Masraflar ve Giderler</Text>
                        <Text style={s.menuDesc}>Tüm harcamaları görüntüle ve yönet</Text>
                    </View>
                    <Icon name="chevron-right" size={24} color="#CBD5E1" />
                </TouchableOpacity>

            </ScrollView>
        </View>
    );
}

const s = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#F8FAFC' },
    retryBtn: { marginTop: 20, padding: 12, backgroundColor: '#0F172A', borderRadius: 12, paddingHorizontal: 24 },
    retryTxt: { color: '#fff', fontWeight: 'bold' },
    
    summaryCard: { backgroundColor: '#fff', borderRadius: 24, padding: 24, alignItems: 'center', marginBottom: 30, shadowColor: '#94A3B8', shadowOffset: { width: 0, height: 10 }, shadowOpacity: 0.1, shadowRadius: 20, elevation: 5 },
    summaryTitle: { fontSize: 14, fontWeight: '700', color: '#64748B', marginTop: 12, marginBottom: 8 },
    summaryAmount: { fontSize: 32, fontWeight: '900', color: '#0F172A', letterSpacing: -1 },

    sectionTitle: { fontSize: 14, fontWeight: '800', color: '#64748B', marginBottom: 16, marginLeft: 4, letterSpacing: 0.5 },
    
    menuBtn: { flexDirection: 'row', alignItems: 'center', backgroundColor: '#fff', padding: 16, borderRadius: 20, marginBottom: 12, shadowColor: '#94A3B8', shadowOffset: { width: 0, height: 4 }, shadowOpacity: 0.05, shadowRadius: 10, elevation: 2 },
    menuIconBox: { width: 50, height: 50, borderRadius: 16, alignItems: 'center', justifyContent: 'center', marginRight: 16 },
    menuContent: { flex: 1 },
    menuTitle: { fontSize: 16, fontWeight: '800', color: '#0F172A', marginBottom: 4 },
    menuDesc: { fontSize: 13, color: '#64748B', fontWeight: '500' }
});
