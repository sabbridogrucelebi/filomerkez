import React, { useContext } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, ScrollView } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import Icon from '@expo/vector-icons/Ionicons';
import { LinearGradient } from 'expo-linear-gradient';
import { AuthContext } from '../context/AuthContext';
import SpaceWaves from '../components/SpaceWaves';
import useStatusBarStyle from '../hooks/useStatusBarStyle';

export default function ProfileScreen({ navigation }) {
    useStatusBarStyle('dark-content');
    const { userInfo, logout } = useContext(AuthContext);

    // İsimden baş harfleri al
    const getInitials = (name) => {
        if (!name) return 'U';
        return name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
    };

    // İngilizce rolleri Türkçeye çevir
    const getRoleName = (role) => {
        if (userInfo?.is_company_admin) return 'Firma Yöneticisi';
        const roles = {
            'operation': 'Operasyon',
            'accounting': 'Muhasebe',
            'viewer': 'Gözlemci'
        };
        return roles[role] || role || 'Personel';
    };

    return (
        <View style={s.container}>
            <SafeAreaView style={{ flex: 1 }} edges={['top', 'left', 'right']}>
                <ScrollView style={{ flex: 1 }} contentContainerStyle={s.scrollContent} showsVerticalScrollIndicator={false}>
                    
                    <View style={s.header}>
                        <Text style={s.headerTitle}>Profil</Text>
                        <Text style={s.headerSub}>Hesap ve güvenlik ayarları</Text>
                    </View>

                    <View style={s.profileCardWrapper}>
                        <SpaceWaves />
                        <View style={s.profileCardContent}>
                            <View style={s.avatarWrap}>
                                <Text style={s.avatarText}>{getInitials(userInfo?.name)}</Text>
                            </View>
                            <View style={s.infoWrap}>
                                <Text style={s.userName}>{userInfo?.name || 'Kullanıcı'}</Text>
                                <Text style={s.userEmail}>{userInfo?.email || 'kullanici@servispilot.com'}</Text>
                                <View style={s.roleBadge}>
                                    <Text style={s.roleText}>{getRoleName(userInfo?.role)}</Text>
                                </View>
                            </View>
                        </View>
                    </View>

                    <View style={s.section}>
                        <Text style={s.sectionTitle}>GENEL AYARLAR</Text>
                        <View style={s.listCard}>
                            <TouchableOpacity style={s.listItem} activeOpacity={0.6} onPress={() => navigation.navigate('AccountInfo')}>
                                <View style={[s.listIcon, { backgroundColor: '#3B82F615' }]}>
                                    <Icon name="person-outline" size={20} color="#3B82F6" />
                                </View>
                                <Text style={s.listText}>Hesap Bilgileri</Text>
                                <Icon name="chevron-forward" size={18} color="#CBD5E1" />
                            </TouchableOpacity>
                            <View style={s.divider} />
                            
                            <TouchableOpacity style={s.listItem} activeOpacity={0.6} onPress={() => navigation.navigate('NotificationSettings')}>
                                <View style={[s.listIcon, { backgroundColor: '#10B98115' }]}>
                                    <Icon name="notifications-outline" size={20} color="#10B981" />
                                </View>
                                <Text style={s.listText}>Bildirim Tercihleri</Text>
                                <Icon name="chevron-forward" size={18} color="#CBD5E1" />
                            </TouchableOpacity>
                            <View style={s.divider} />
                            
                            <TouchableOpacity style={s.listItem} activeOpacity={0.6} onPress={() => navigation.navigate('Security')}>
                                <View style={[s.listIcon, { backgroundColor: '#8B5CF615' }]}>
                                    <Icon name="shield-checkmark-outline" size={20} color="#8B5CF6" />
                                </View>
                                <Text style={s.listText}>Güvenlik ve Şifre</Text>
                                <Icon name="chevron-forward" size={18} color="#CBD5E1" />
                            </TouchableOpacity>
                        </View>
                    </View>

                    <View style={s.section}>
                        <Text style={s.sectionTitle}>SİSTEM BİLGİSİ</Text>
                        <View style={s.listCard}>
                            <TouchableOpacity style={s.listItem} activeOpacity={0.6} onPress={() => navigation.navigate('Support')}>
                                <View style={[s.listIcon, { backgroundColor: '#F59E0B15' }]}>
                                    <Icon name="help-circle-outline" size={20} color="#F59E0B" />
                                </View>
                                <Text style={s.listText}>Yardım ve Destek</Text>
                                <Icon name="chevron-forward" size={18} color="#CBD5E1" />
                            </TouchableOpacity>
                        </View>
                    </View>

                    <TouchableOpacity style={s.logoutBtn} onPress={logout} activeOpacity={0.8}>
                        <LinearGradient colors={['#EF4444', '#B91C1C']} style={s.logoutGradient}>
                            <Icon name="log-out-outline" size={20} color="#FFF" style={{ marginRight: 8 }} />
                            <Text style={s.logoutText}>Güvenli Çıkış Yap</Text>
                        </LinearGradient>
                    </TouchableOpacity>

                    <View style={{ height: 120 }} />
                </ScrollView>
            </SafeAreaView>
        </View>
    );
}

const s = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#FFFFFF' },
    scrollContent: { paddingHorizontal: 20, paddingTop: 10 },
    
    header: { marginBottom: 16 },
    headerTitle: { fontSize: 26, fontWeight: '900', color: '#0F172A', letterSpacing: -1 },
    headerSub: { fontSize: 13, color: '#64748B', fontWeight: '500', marginTop: 2 },

    profileCardWrapper: { marginHorizontal: -20, marginBottom: 20, overflow: 'hidden', backgroundColor: '#020617' },
    profileCardContent: { flexDirection: 'row', padding: 20, alignItems: 'center', backgroundColor: 'transparent' },
    avatarWrap: { width: 56, height: 56, borderRadius: 28, backgroundColor: 'rgba(59,130,246,0.2)', alignItems: 'center', justifyContent: 'center', marginRight: 16, borderWidth: 2, borderColor: 'rgba(59,130,246,0.5)' },
    avatarText: { fontSize: 20, fontWeight: '900', color: '#FFF' },
    infoWrap: { flex: 1 },
    userName: { fontSize: 18, fontWeight: '800', color: '#FFF', marginBottom: 2 },
    userEmail: { fontSize: 12, color: '#94A3B8', marginBottom: 6 },
    roleBadge: { alignSelf: 'flex-start', backgroundColor: 'rgba(59,130,246,0.15)', paddingHorizontal: 10, paddingVertical: 4, borderRadius: 6, borderWidth: 1, borderColor: 'rgba(59,130,246,0.3)' },
    roleText: { fontSize: 10, fontWeight: '800', color: '#60A5FA', letterSpacing: 0.5, textTransform: 'uppercase' },

    section: { marginBottom: 16 },
    sectionTitle: { fontSize: 11, fontWeight: '800', color: '#94A3B8', marginBottom: 8, marginLeft: 8, letterSpacing: 1 },
    listCard: { backgroundColor: '#F8FAFC', borderRadius: 20, overflow: 'hidden', borderWidth: 1, borderColor: '#F1F5F9' },
    listItem: { flexDirection: 'row', alignItems: 'center', padding: 12 },
    listIcon: { width: 36, height: 36, borderRadius: 12, alignItems: 'center', justifyContent: 'center', marginRight: 12 },
    listText: { flex: 1, fontSize: 14, fontWeight: '700', color: '#1E293B' },
    divider: { height: 1, backgroundColor: '#F1F5F9', marginLeft: 60 },

    logoutBtn: { marginTop: 4, borderRadius: 20, overflow: 'hidden', shadowColor: '#EF4444', shadowOffset: { width: 0, height: 8 }, shadowopacity: 1, shadowRadius: 10, elevation: 6 },
    logoutGradient: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', paddingVertical: 14 },
    logoutText: { fontSize: 15, fontWeight: '800', color: '#FFF', letterSpacing: 0.5 }
});
