import React, { useState, useEffect, useCallback } from 'react';
import { View, Text, StyleSheet, ScrollView, ActivityIndicator, RefreshControl } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import api from '../api/axios';
import SpaceWaves from '../components/SpaceWaves';
import Icon from '@expo/vector-icons/MaterialCommunityIcons';
import { useFocusEffect } from '@react-navigation/native';

export default function ParentPaymentScreen() {
    const [isLoading, setIsLoading] = useState(true);
    const [isRefreshing, setIsRefreshing] = useState(false);
    const [data, setData] = useState(null);

    const fetchData = async () => {
        try {
            const response = await api.get('/v1/pilotcell/parent/student-info');
            setData(response.data);
        } catch (error) {
            console.log('Error fetching student info:', error);
        } finally {
            setIsLoading(false);
            setIsRefreshing(false);
        }
    };

    useFocusEffect(
        useCallback(() => {
            fetchData();
        }, [])
    );

    const onRefresh = () => {
        setIsRefreshing(true);
        fetchData();
    };

    const formatCurrency = (amount) => {
        return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' }).format(amount);
    };

    if (isLoading) {
        return (
            <SafeAreaView style={styles.container} edges={['top']}>
                <SpaceWaves />
                <View style={styles.centerContent}>
                    <ActivityIndicator size="large" color="#8B5CF6" />
                    <Text style={styles.loadingText}>Borç bilgileri yükleniyor...</Text>
                </View>
            </SafeAreaView>
        );
    }

    if (!data || !data.debts) {
        return (
            <SafeAreaView style={styles.container} edges={['top']}>
                <SpaceWaves />
                <View style={styles.centerContent}>
                    <Icon name="alert-circle-outline" size={48} color="#EF4444" />
                    <Text style={styles.loadingText}>Bilgiler alınamadı.</Text>
                </View>
            </SafeAreaView>
        );
    }

    const { totals, debts } = data;

    return (
        <SafeAreaView style={styles.container} edges={['top']}>
            <SpaceWaves />
            
            <View style={styles.header}>
                <Text style={styles.headerTitle}>Servis Ücretleri</Text>
                <Text style={styles.headerSubtitle}>Ödeme ve Borç Takibi</Text>
            </View>

            <ScrollView 
                contentContainerStyle={styles.scrollContent}
                refreshControl={<RefreshControl refreshing={isRefreshing} onRefresh={onRefresh} tintColor="#8B5CF6" />}
            >
                {/* Genel Toplamlar Kartı */}
                <View style={styles.summaryCard}>
                    <View style={styles.summaryRow}>
                        <View style={styles.summaryItem}>
                            <Text style={styles.summaryLabel}>Toplam Borç</Text>
                            <Text style={[styles.summaryValue, { color: '#fff' }]}>{formatCurrency(totals.total_debt)}</Text>
                        </View>
                        <View style={styles.summaryDivider} />
                        <View style={styles.summaryItem}>
                            <Text style={styles.summaryLabel}>Ödenen</Text>
                            <Text style={[styles.summaryValue, { color: '#10B981' }]}>{formatCurrency(totals.total_paid)}</Text>
                        </View>
                    </View>
                    <View style={styles.summaryBottomRow}>
                        <Text style={styles.summaryLabel}>Kalan Bakiye</Text>
                        <Text style={[styles.summaryTotalValue, { color: totals.remaining_debt > 0 ? '#EF4444' : '#10B981' }]}>
                            {formatCurrency(totals.remaining_debt)}
                        </Text>
                    </View>
                </View>

                {/* Aylık Taksitler Listesi */}
                <Text style={styles.sectionTitle}>Aylık Ödemeler</Text>

                {debts.length === 0 ? (
                    <View style={styles.emptyState}>
                        <Icon name="check-circle-outline" size={48} color="#10B981" />
                        <Text style={styles.emptyText}>Hiçbir borç kaydı bulunmuyor.</Text>
                    </View>
                ) : (
                    debts.map((debt, index) => {
                        const isPaid = debt.is_paid || debt.paid_amount >= debt.amount;
                        const remaining = debt.amount - debt.paid_amount;
                        
                        return (
                            <View key={index} style={styles.debtCard}>
                                <View style={styles.debtHeader}>
                                    <View style={styles.monthBadge}>
                                        <Text style={styles.monthText}>{debt.month_name} {debt.year}</Text>
                                    </View>
                                    <View style={[styles.statusBadge, isPaid ? styles.statusPaidBg : styles.statusUnpaidBg]}>
                                        <Text style={[styles.statusText, isPaid ? styles.statusPaidText : styles.statusUnpaidText]}>
                                            {isPaid ? 'ÖDENDİ' : 'ÖDENMEDİ'}
                                        </Text>
                                    </View>
                                </View>

                                <View style={styles.debtBody}>
                                    <View style={styles.debtColumn}>
                                        <Text style={styles.debtLabel}>Tutar</Text>
                                        <Text style={styles.debtAmount}>{formatCurrency(debt.amount)}</Text>
                                    </View>
                                    <View style={styles.debtColumn}>
                                        <Text style={styles.debtLabel}>Ödenen</Text>
                                        <Text style={[styles.debtAmount, { color: '#10B981' }]}>{formatCurrency(debt.paid_amount)}</Text>
                                    </View>
                                    <View style={styles.debtColumn}>
                                        <Text style={styles.debtLabel}>Kalan</Text>
                                        <Text style={[styles.debtAmount, { color: remaining > 0 ? '#EF4444' : '#64748B' }]}>{formatCurrency(Math.max(0, remaining))}</Text>
                                    </View>
                                </View>
                            </View>
                        );
                    })
                )}
                <View style={{ height: 80 }} />
            </ScrollView>
        </SafeAreaView>
    );
}

const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#020617' },
    centerContent: { flex: 1, justifyContent: 'center', alignItems: 'center' },
    loadingText: { color: '#94A3B8', marginTop: 12, fontSize: 14, fontWeight: '500' },
    
    header: { paddingHorizontal: 20, paddingTop: 20, paddingBottom: 10, zIndex: 10 },
    headerTitle: { color: '#fff', fontSize: 24, fontWeight: '900', letterSpacing: 0.5 },
    headerSubtitle: { color: '#8B5CF6', fontSize: 13, fontWeight: '600', marginTop: 2 },
    
    scrollContent: { padding: 20, paddingBottom: 120, zIndex: 10 },
    
    summaryCard: { backgroundColor: 'rgba(15, 23, 42, 0.8)', borderRadius: 24, padding: 20, borderWidth: 1, borderColor: 'rgba(255,255,255,0.05)', marginBottom: 24 },
    summaryRow: { flexDirection: 'row', justifyContent: 'space-between', marginBottom: 20 },
    summaryItem: { flex: 1, alignItems: 'center' },
    summaryDivider: { width: 1, backgroundColor: 'rgba(255,255,255,0.1)' },
    summaryLabel: { color: '#94A3B8', fontSize: 12, fontWeight: '600', marginBottom: 6, textTransform: 'uppercase', letterSpacing: 0.5 },
    summaryValue: { fontSize: 20, fontWeight: '800' },
    summaryBottomRow: { alignItems: 'center', paddingTop: 20, borderTopWidth: 1, borderTopColor: 'rgba(255,255,255,0.1)' },
    summaryTotalValue: { fontSize: 28, fontWeight: '900' },
    
    sectionTitle: { color: '#fff', fontSize: 16, fontWeight: '700', marginBottom: 16, marginLeft: 4 },
    
    debtCard: { backgroundColor: 'rgba(30, 41, 59, 0.6)', borderRadius: 20, padding: 16, marginBottom: 12, borderWidth: 1, borderColor: 'rgba(255,255,255,0.05)' },
    debtHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16, borderBottomWidth: 1, borderBottomColor: 'rgba(255,255,255,0.05)', paddingBottom: 12 },
    monthBadge: { backgroundColor: 'rgba(139, 92, 246, 0.1)', paddingHorizontal: 12, paddingVertical: 6, borderRadius: 10, borderWidth: 1, borderColor: 'rgba(139, 92, 246, 0.2)' },
    monthText: { color: '#A78BFA', fontSize: 13, fontWeight: '700' },
    statusBadge: { paddingHorizontal: 10, paddingVertical: 4, borderRadius: 8 },
    statusPaidBg: { backgroundColor: 'rgba(16, 185, 129, 0.15)' },
    statusUnpaidBg: { backgroundColor: 'rgba(239, 68, 68, 0.15)' },
    statusText: { fontSize: 11, fontWeight: '800' },
    statusPaidText: { color: '#34D399' },
    statusUnpaidText: { color: '#F87171' },
    
    debtBody: { flexDirection: 'row', justifyContent: 'space-between' },
    debtColumn: { alignItems: 'center' },
    debtLabel: { color: '#64748B', fontSize: 11, fontWeight: '600', marginBottom: 4 },
    debtAmount: { color: '#fff', fontSize: 15, fontWeight: '700' },
    
    emptyState: { backgroundColor: 'rgba(15, 23, 42, 0.6)', padding: 30, borderRadius: 24, alignItems: 'center', borderWidth: 1, borderColor: 'rgba(16, 185, 129, 0.2)' },
    emptyText: { color: '#94A3B8', fontSize: 14, textAlign: 'center', marginTop: 12 }
});
