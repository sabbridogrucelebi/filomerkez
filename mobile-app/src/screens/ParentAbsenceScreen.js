import React, { useState, useEffect, useContext, useCallback } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, Alert, ActivityIndicator, Platform, ScrollView } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import MaterialCommunityIcons from '@expo/vector-icons/MaterialCommunityIcons';
import { AuthContext } from '../context/AuthContext';
import SpaceWaves from '../components/SpaceWaves';
import api from '../api/axios';

const DAYS_TR = ['Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt', 'Paz'];
const MONTHS_TR = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];

function formatDate(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

function getMonthDays(year, month) {
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const days = [];
    
    // Monday=0 ... Sunday=6
    let startDay = firstDay.getDay() - 1;
    if (startDay < 0) startDay = 6;
    
    // Empty slots for days before month start
    for (let i = 0; i < startDay; i++) {
        days.push(null);
    }
    
    for (let d = 1; d <= lastDay.getDate(); d++) {
        days.push(new Date(year, month, d));
    }
    
    return days;
}

export default function ParentAbsenceScreen() {
    const { userInfo } = useContext(AuthContext);
    const [isLoading, setIsLoading] = useState(true);
    const [isSaving, setIsSaving] = useState(false);
    const [studentName, setStudentName] = useState('');
    const [absenceDates, setAbsenceDates] = useState(new Set());
    const [currentMonth, setCurrentMonth] = useState(new Date().getMonth());
    const [currentYear, setCurrentYear] = useState(new Date().getFullYear());

    const today = new Date();
    const todayStr = formatDate(today);

    const fetchAbsences = useCallback(async () => {
        try {
            const res = await api.get('/v1/pilotcell/parent/absences');
            if (res.data?.success) {
                setStudentName(res.data.student_name || '');
                setAbsenceDates(new Set(res.data.absences || []));
            }
        } catch (error) {
            console.error('Devamsızlık çekilemedi', error);
        } finally {
            setIsLoading(false);
        }
    }, []);

    useEffect(() => {
        fetchAbsences();
    }, [fetchAbsences]);

    const toggleDate = async (dateStr) => {
        const isAbsent = absenceDates.has(dateStr);
        
        setIsSaving(true);
        try {
            const res = await api.post('/v1/pilotcell/parent/absences/toggle', { date: dateStr });
            if (res.data?.success) {
                const newSet = new Set(absenceDates);
                if (res.data.action === 'added') {
                    newSet.add(dateStr);
                } else {
                    newSet.delete(dateStr);
                }
                setAbsenceDates(newSet);
                
                Alert.alert(
                    res.data.action === 'added' ? '✓ İşaretlendi' : '✓ Kaldırıldı',
                    res.data.message
                );
            }
        } catch (error) {
            Alert.alert('Hata', 'İşlem sırasında bir hata oluştu.');
        } finally {
            setIsSaving(false);
        }
    };

    const goToPrevMonth = () => {
        const now = new Date();
        // Don't go before current month
        if (currentYear === now.getFullYear() && currentMonth <= now.getMonth()) return;
        if (currentMonth === 0) {
            setCurrentMonth(11);
            setCurrentYear(currentYear - 1);
        } else {
            setCurrentMonth(currentMonth - 1);
        }
    };

    const goToNextMonth = () => {
        if (currentMonth === 11) {
            setCurrentMonth(0);
            setCurrentYear(currentYear + 1);
        } else {
            setCurrentMonth(currentMonth + 1);
        }
    };

    const monthDays = getMonthDays(currentYear, currentMonth);
    const absenceCount = absenceDates.size;

    return (
        <SafeAreaView style={styles.container} edges={['top']}>
            <SpaceWaves />
            <ScrollView style={{ flex: 1, zIndex: 10 }} contentContainerStyle={{ paddingBottom: 120 }}>
                {/* Header */}
                <View style={styles.header}>
                    <Text style={styles.headerTitle}>Gelmeyecek</Text>
                    {studentName ? <Text style={styles.headerSub}>{studentName}</Text> : null}
                </View>

                {/* Info Card */}
                <View style={styles.infoCard}>
                    <MaterialCommunityIcons name="information-outline" size={20} color="#38BDF8" />
                    <Text style={styles.infoText}>
                        Öğrencinizin servise binmeyeceği günleri aşağıdaki takvimden seçin. Seçilen günlerde şoför/hostes bilgilendirilecek ve bildirim gönderilmeyecektir.
                    </Text>
                </View>

                {/* Stats */}
                <View style={styles.statsCard}>
                    <View style={styles.statItem}>
                        <Text style={styles.statNum}>{absenceCount}</Text>
                        <Text style={styles.statLabel}>Seçili Gün</Text>
                    </View>
                </View>

                {isLoading ? (
                    <View style={styles.loader}><ActivityIndicator size="large" color="#8B5CF6" /></View>
                ) : (
                    <>
                        {/* Month Navigation */}
                        <View style={styles.monthNav}>
                            <TouchableOpacity onPress={goToPrevMonth} style={styles.monthBtn}>
                                <MaterialCommunityIcons name="chevron-left" size={28} color="#FFF" />
                            </TouchableOpacity>
                            <Text style={styles.monthTitle}>{MONTHS_TR[currentMonth]} {currentYear}</Text>
                            <TouchableOpacity onPress={goToNextMonth} style={styles.monthBtn}>
                                <MaterialCommunityIcons name="chevron-right" size={28} color="#FFF" />
                            </TouchableOpacity>
                        </View>

                        {/* Day Headers */}
                        <View style={styles.dayHeaders}>
                            {DAYS_TR.map(d => (
                                <View key={d} style={styles.dayHeaderCell}>
                                    <Text style={styles.dayHeaderText}>{d}</Text>
                                </View>
                            ))}
                        </View>

                        {/* Calendar Grid */}
                        <View style={styles.calendarGrid}>
                            {monthDays.map((day, idx) => {
                                if (!day) {
                                    return <View key={`empty-${idx}`} style={styles.dayCell} />;
                                }

                                const dateStr = formatDate(day);
                                const isToday = dateStr === todayStr;
                                const isPast = day < new Date(today.getFullYear(), today.getMonth(), today.getDate());
                                const isAbsent = absenceDates.has(dateStr);

                                return (
                                    <TouchableOpacity
                                        key={dateStr}
                                        style={[
                                            styles.dayCell,
                                            isToday && styles.dayCellToday,
                                            isAbsent && styles.dayCellAbsent,
                                            isPast && styles.dayCellPast,
                                        ]}
                                        onPress={() => {
                                            if (isPast) return;
                                            if (isSaving) return;
                                            toggleDate(dateStr);
                                        }}
                                        disabled={isPast || isSaving}
                                    >
                                        <Text style={[
                                            styles.dayText,
                                            isToday && styles.dayTextToday,
                                            isAbsent && styles.dayTextAbsent,
                                            isPast && styles.dayTextPast,
                                        ]}>
                                            {day.getDate()}
                                        </Text>
                                        {isAbsent && (
                                            <MaterialCommunityIcons name="close-circle" size={12} color="#EF4444" style={{ position: 'absolute', top: 2, right: 2 }} />
                                        )}
                                        {isToday && !isAbsent && (
                                            <View style={styles.todayDot} />
                                        )}
                                    </TouchableOpacity>
                                );
                            })}
                        </View>

                        {/* Legend */}
                        <View style={styles.legend}>
                            <View style={styles.legendItem}>
                                <View style={[styles.legendDot, { backgroundColor: '#8B5CF6' }]} />
                                <Text style={styles.legendText}>Bugün</Text>
                            </View>
                            <View style={styles.legendItem}>
                                <View style={[styles.legendDot, { backgroundColor: '#EF4444' }]} />
                                <Text style={styles.legendText}>Gelmeyecek</Text>
                            </View>
                            <View style={styles.legendItem}>
                                <View style={[styles.legendDot, { backgroundColor: 'rgba(255,255,255,0.2)' }]} />
                                <Text style={styles.legendText}>Geçmiş</Text>
                            </View>
                        </View>
                    </>
                )}
            </ScrollView>

            {isSaving && (
                <View style={styles.savingOverlay}>
                    <ActivityIndicator size="small" color="#FFF" />
                    <Text style={styles.savingText}>Kaydediliyor...</Text>
                </View>
            )}
        </SafeAreaView>
    );
}

const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#020617' },
    header: { paddingHorizontal: 20, paddingTop: 10, paddingBottom: 10, alignItems: 'center' },
    headerTitle: { fontSize: 24, fontWeight: '900', color: '#FFF', letterSpacing: 0.5 },
    headerSub: { fontSize: 14, color: '#A78BFA', fontWeight: '700', marginTop: 4 },

    infoCard: { flexDirection: 'row', marginHorizontal: 20, backgroundColor: 'rgba(56, 189, 248, 0.1)', borderRadius: 16, padding: 14, marginBottom: 16, borderWidth: 1, borderColor: 'rgba(56, 189, 248, 0.3)', alignItems: 'flex-start', gap: 10 },
    infoText: { flex: 1, color: '#94A3B8', fontSize: 12, lineHeight: 18 },

    statsCard: { flexDirection: 'row', justifyContent: 'center', marginHorizontal: 20, marginBottom: 20 },
    statItem: { alignItems: 'center', backgroundColor: 'rgba(239, 68, 68, 0.15)', paddingHorizontal: 24, paddingVertical: 10, borderRadius: 14, borderWidth: 1, borderColor: 'rgba(239, 68, 68, 0.3)' },
    statNum: { fontSize: 28, fontWeight: '900', color: '#EF4444' },
    statLabel: { fontSize: 11, color: '#94A3B8', fontWeight: '600', marginTop: 2 },

    loader: { flex: 1, justifyContent: 'center', alignItems: 'center', paddingTop: 80 },

    monthNav: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginHorizontal: 20, marginBottom: 16 },
    monthBtn: { padding: 8, backgroundColor: 'rgba(255,255,255,0.1)', borderRadius: 12 },
    monthTitle: { fontSize: 18, fontWeight: '800', color: '#FFF' },

    dayHeaders: { flexDirection: 'row', marginHorizontal: 20, marginBottom: 8 },
    dayHeaderCell: { flex: 1, alignItems: 'center' },
    dayHeaderText: { color: '#64748B', fontSize: 12, fontWeight: '700' },

    calendarGrid: { flexDirection: 'row', flexWrap: 'wrap', marginHorizontal: 20 },
    dayCell: { width: '14.28%', aspectRatio: 1, alignItems: 'center', justifyContent: 'center', position: 'relative' },
    dayCellToday: { backgroundColor: 'rgba(139, 92, 246, 0.2)', borderRadius: 12 },
    dayCellAbsent: { backgroundColor: 'rgba(239, 68, 68, 0.2)', borderRadius: 12, borderWidth: 1, borderColor: 'rgba(239, 68, 68, 0.5)' },
    dayCellPast: { opacity: 1 },
    dayText: { color: '#E2E8F0', fontSize: 15, fontWeight: '700' },
    dayTextToday: { color: '#A78BFA', fontWeight: '900' },
    dayTextAbsent: { color: '#EF4444', fontWeight: '900' },
    dayTextPast: { color: '#475569' },
    todayDot: { position: 'absolute', bottom: 4, width: 5, height: 5, borderRadius: 3, backgroundColor: '#8B5CF6' },

    legend: { flexDirection: 'row', justifyContent: 'center', gap: 20, marginTop: 24, marginHorizontal: 20 },
    legendItem: { flexDirection: 'row', alignItems: 'center', gap: 6 },
    legendDot: { width: 10, height: 10, borderRadius: 5 },
    legendText: { color: '#94A3B8', fontSize: 11, fontWeight: '600' },

    savingOverlay: { position: 'absolute', top: 12, alignSelf: 'center', flexDirection: 'row', alignItems: 'center', backgroundColor: 'rgba(139, 92, 246, 0.9)', paddingHorizontal: 20, paddingVertical: 10, borderRadius: 20, gap: 8 },
    savingText: { color: '#FFF', fontSize: 13, fontWeight: '700' },
});
