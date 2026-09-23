import React, { useState } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, ScrollView, Switch, Platform } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import Icon from '@expo/vector-icons/Ionicons';
import { LinearGradient } from 'expo-linear-gradient';
import SpaceWaves from '../components/SpaceWaves';

export default function NotificationSettingsScreen({ navigation }) {
    // Local state for UI switches
    const [tripsNotif, setTripsNotif] = useState(true);
    const [maintenanceNotif, setMaintenanceNotif] = useState(true);
    const [documentsNotif, setDocumentsNotif] = useState(true);
    const [systemNotif, setSystemNotif] = useState(false);

    const renderToggle = (icon, color, title, desc, value, onValueChange) => (
        <View style={s.toggleCard}>
            <View style={[s.iconWrap, { backgroundColor: `${color}20` }]}>
                <Icon name={icon} size={22} color={color} />
            </View>
            <View style={s.textWrap}>
                <Text style={s.toggleTitle}>{title}</Text>
                <Text style={s.toggleDesc}>{desc}</Text>
            </View>
            <Switch
                trackColor={{ false: 'rgba(255,255,255,0.1)', true: color }}
                thumbColor={value ? '#FFFFFF' : '#94A3B8'}
                ios_backgroundColor="rgba(255,255,255,0.1)"
                onValueChange={onValueChange}
                value={value}
            />
        </View>
    );

    return (
        <View style={s.container}>
            <SpaceWaves />
            <SafeAreaView style={{ flex: 1, paddingTop: Platform.OS === 'android' ? 30 : 40 }}>
                {/* Header */}
                <View style={s.header}>
                    <TouchableOpacity onPress={() => navigation.goBack()} style={s.backBtn}>
                        <Icon name="arrow-back" size={24} color="#FFF" />
                    </TouchableOpacity>
                    <View style={s.headerTitleWrap}>
                        <Text style={s.headerTitle}>Bildirim Tercihleri</Text>
                        <Text style={s.headerSub}>İLETİŞİM VE UYARI AYARLARI</Text>
                    </View>
                </View>

                <ScrollView contentContainerStyle={s.scrollContent} showsVerticalScrollIndicator={false}>
                    
                    <View style={s.infoCard}>
                        <View style={s.infoIconWrap}>
                            <Icon name="information-circle" size={24} color="#38BDF8" />
                        </View>
                        <View style={{ flex: 1 }}>
                            <Text style={s.infoTitle}>Akıllı Bildirimler</Text>
                            <Text style={s.infoText}>
                                Bildirim tercihlerinizi buradan yöneterek yalnızca sizin için önemli olan uyarıları alabilirsiniz.
                            </Text>
                        </View>
                    </View>

                    <Text style={s.sectionHeader}>OPERASYONEL BİLDİRİMLER</Text>

                    {renderToggle(
                        'car-sport-outline', 
                        '#10B981', 
                        'Sefer Atamaları', 
                        'Bana yeni bir sefer atandığında anında haber ver.', 
                        tripsNotif, 
                        setTripsNotif
                    )}

                    {renderToggle(
                        'build-outline', 
                        '#F59E0B', 
                        'Araç Bakım Uyarıları', 
                        'Filodaki araçların bakımı yaklaştığında bildir.', 
                        maintenanceNotif, 
                        setMaintenanceNotif
                    )}

                    {renderToggle(
                        'document-text-outline', 
                        '#8B5CF6', 
                        'Belge Bitiş Uyarıları', 
                        'Sigorta/Muayene gibi belgelerin süresi dolmadan önce hatırlat.', 
                        documentsNotif, 
                        setDocumentsNotif
                    )}

                    <Text style={[s.sectionHeader, { marginTop: 10 }]}>DİĞER BİLDİRİMLER</Text>

                    {renderToggle(
                        'megaphone-outline', 
                        '#EC4899', 
                        'Sistem Duyuruları', 
                        'ServisPilot güncellemelerinden haberdar ol.', 
                        systemNotif, 
                        setSystemNotif
                    )}

                    <TouchableOpacity style={s.saveBtn} activeOpacity={0.8} onPress={() => navigation.goBack()}>
                        <LinearGradient colors={['#6366F1', '#4F46E5']} style={s.saveGradient}>
                            <Icon name="checkmark-circle-outline" size={22} color="#FFF" style={{ marginRight: 8 }} />
                            <Text style={s.saveText}>Tercihleri Kaydet</Text>
                        </LinearGradient>
                    </TouchableOpacity>

                    <View style={{ height: 120 }} />
                </ScrollView>
            </SafeAreaView>
        </View>
    );
}

const s = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#020617' },
    
    header: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: 20, paddingTop: 20, paddingBottom: 20 },
    backBtn: { width: 44, height: 44, borderRadius: 22, backgroundColor: 'rgba(255,255,255,0.1)', alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: 'rgba(255,255,255,0.05)', marginRight: 15 },
    headerTitleWrap: { flex: 1 },
    headerTitle: { fontSize: 24, fontWeight: '900', color: '#FFFFFF', letterSpacing: -0.5 },
    headerSub: { fontSize: 11, color: '#94A3B8', fontWeight: '800', marginTop: 2, letterSpacing: 0.5 },

    scrollContent: { paddingHorizontal: 20, paddingTop: 10 },

    infoCard: { flexDirection: 'row', backgroundColor: 'rgba(56, 189, 248, 0.1)', padding: 16, borderRadius: 20, marginBottom: 24, borderWidth: 1, borderColor: 'rgba(56, 189, 248, 0.2)' },
    infoIconWrap: { width: 40, height: 40, borderRadius: 20, backgroundColor: 'rgba(56, 189, 248, 0.2)', alignItems: 'center', justifyContent: 'center', marginRight: 12 },
    infoTitle: { fontSize: 14, fontWeight: '800', color: '#7DD3FC', marginBottom: 4 },
    infoText: { fontSize: 13, color: '#BAE6FD', lineHeight: 18 },

    sectionHeader: { fontSize: 12, fontWeight: '800', color: '#64748B', letterSpacing: 1, marginBottom: 12, marginLeft: 4 },

    toggleCard: { flexDirection: 'row', alignItems: 'center', backgroundColor: 'rgba(15, 23, 42, 0.7)', padding: 16, borderRadius: 20, marginBottom: 12, borderWidth: 1, borderColor: 'rgba(255,255,255,0.05)', shadowColor: '#000', shadowOffset: { width: 0, height: 4 }, shadowOpacity: 0.2, shadowRadius: 10, elevation: 5 },
    iconWrap: { width: 46, height: 46, borderRadius: 16, alignItems: 'center', justifyContent: 'center', marginRight: 14 },
    textWrap: { flex: 1, paddingRight: 10 },
    toggleTitle: { fontSize: 15, fontWeight: '800', color: '#FFFFFF', marginBottom: 4 },
    toggleDesc: { fontSize: 12, color: '#94A3B8', lineHeight: 16 },

    saveBtn: { borderRadius: 20, overflow: 'hidden', marginTop: 20, shadowColor: '#6366F1', shadowOffset: { width: 0, height: 8 }, shadowOpacity: 0.4, shadowRadius: 15, elevation: 8 },
    saveGradient: { flexDirection: 'row', paddingVertical: 18, alignItems: 'center', justifyContent: 'center' },
    saveText: { fontSize: 16, fontWeight: '800', color: '#FFF', letterSpacing: 0.5 },
});
