import React, { useContext, useRef, useEffect } from 'react';
import { View, Text, StyleSheet, ScrollView, TouchableOpacity, Animated, Easing, Switch } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import Icon from '@expo/vector-icons/MaterialCommunityIcons';
import { LinearGradient } from 'expo-linear-gradient';
import { BlurView } from 'expo-blur';
import Constants from 'expo-constants';
import { AuthContext } from '../context/AuthContext';

export default function SettingsScreen({ navigation }) {
    const { userInfo, logout } = useContext(AuthContext);

    // Animations
    const blob1Anim = useRef(new Animated.Value(0)).current;
    const blob2Anim = useRef(new Animated.Value(0)).current;

    useEffect(() => {
        const loop1 = Animated.loop(
            Animated.sequence([
                Animated.timing(blob1Anim, { toValue: 1, duration: 8000, easing: Easing.inOut(Easing.sin), useNativeDriver: true }),
                Animated.timing(blob1Anim, { toValue: 0, duration: 8000, easing: Easing.inOut(Easing.sin), useNativeDriver: true })
            ])
        );
        const loop2 = Animated.loop(
            Animated.sequence([
                Animated.timing(blob2Anim, { toValue: 1, duration: 10000, easing: Easing.inOut(Easing.sin), useNativeDriver: true }),
                Animated.timing(blob2Anim, { toValue: 0, duration: 10000, easing: Easing.inOut(Easing.sin), useNativeDriver: true })
            ])
        );
        loop1.start();
        loop2.start();
        return () => { loop1.stop(); loop2.stop(); };
    }, []);

    const SettingItem = ({ icon, title, subtitle, color, onPress, toggle, toggleValue, onToggle }) => (
        <TouchableOpacity 
            style={st.settingCard} 
            activeOpacity={0.7}
            onPress={onPress}
            disabled={!onPress}
        >
            <BlurView intensity={30} tint="dark" style={st.settingCardInner}>
                <View style={[st.iconBox, { backgroundColor: `${color}20`, borderColor: `${color}40` }]}>
                    <Icon name={icon} size={24} color={color} />
                </View>
                <View style={st.infoWrap}>
                    <Text style={st.settingTitle}>{title}</Text>
                    {subtitle && <Text style={st.settingSubtitle}>{subtitle}</Text>}
                </View>
                {toggle ? (
                    <Switch
                        value={toggleValue}
                        onValueChange={onToggle}
                        trackColor={{ false: 'rgba(255,255,255,0.1)', true: color }}
                        thumbColor="#FFF"
                    />
                ) : onPress ? (
                    <Icon name="chevron-right" size={24} color="#64748B" />
                ) : null}
            </BlurView>
        </TouchableOpacity>
    );

    return (
        <View style={st.container}>
            {/* Animated Background */}
            <Animated.View style={StyleSheet.absoluteFill}>
                <LinearGradient colors={['#020617', '#0F172A', '#1E1B4B']} style={StyleSheet.absoluteFillObject} />
                <Animated.View style={[st.bgBlob1, { transform: [{ translateY: blob1Anim.interpolate({ inputRange:[0,1], outputRange:[0, 60] }) }] }]} />
                <Animated.View style={[st.bgBlob2, { transform: [{ translateX: blob2Anim.interpolate({ inputRange:[0,1], outputRange:[0, -60] }) }] }]} />
            </Animated.View>

            <SafeAreaView style={{ flex: 1 }} edges={['top']}>
                <View style={st.header}>
                    <TouchableOpacity onPress={() => navigation.goBack()} style={st.backBtn}>
                        <BlurView intensity={30} tint="light" style={StyleSheet.absoluteFillObject} />
                        <Icon name="chevron-left" size={28} color="#FFF" />
                    </TouchableOpacity>
                    <View style={{ flex: 1, alignItems: 'center' }}>
                        <Text style={st.headerTitle}>Ayarlar</Text>
                        <Text style={st.headerSubtitle}>Sistem Yapılandırması</Text>
                    </View>
                    <View style={{ width: 44 }} />
                </View>

                <ScrollView contentContainerStyle={st.scrollContent} showsVerticalScrollIndicator={false}>

                    <Text style={st.sectionTitle}>Hesap ve Profil</Text>
                    <SettingItem 
                        icon="account-outline" 
                        title="Profil Bilgileri" 
                        subtitle="Kişisel bilgilerinizi güncelleyin" 
                        color="#38BDF8"
                        onPress={() => navigation.navigate('Profile', { screen: 'ProfileMain' })}
                    />
                    <SettingItem 
                        icon="shield-lock-outline" 
                        title="Güvenlik" 
                        subtitle="Şifre ve giriş ayarları" 
                        color="#34D399"
                        onPress={() => navigation.navigate('Profile', { screen: 'Security' })}
                    />

                    <Text style={st.sectionTitle}>Uygulama Tercihleri</Text>
                    <SettingItem 
                        icon="bell-outline" 
                        title="Bildirimler" 
                        subtitle="Push ve e-posta bildirimleri" 
                        color="#FBBF24"
                        onPress={() => navigation.navigate('Profile', { screen: 'NotificationSettings' })}
                    />
                    <SettingItem 
                        icon="wrench-outline" 
                        title="Bakım Ayarları" 
                        subtitle="Bakım ve uyarı tercihleri" 
                        color="#A3E635"
                        onPress={() => navigation.navigate('MaintenanceSettings')}
                    />

                    <Text style={st.sectionTitle}>Sistem</Text>
                    <SettingItem 
                        icon="information-outline" 
                        title="Hakkında" 
                        subtitle={`Sürüm v${Constants.expoConfig?.version ?? '-'}`}
                        color="#818CF8"
                    />
                    
                    <TouchableOpacity style={st.logoutBtn} onPress={logout}>
                        <LinearGradient colors={['rgba(239,68,68,0.2)', 'rgba(220,38,38,0.1)']} style={StyleSheet.absoluteFillObject} />
                        <Icon name="logout" size={24} color="#F87171" style={{ marginRight: 10 }} />
                        <Text style={st.logoutTxt}>Çıkış Yap</Text>
                    </TouchableOpacity>
                    
                    <View style={{ height: 120 }} />
                </ScrollView>
            </SafeAreaView>
        </View>
    );
}

const st = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#020617' },
    bgBlob1: { position: 'absolute', top: -50, left: -50, width: 350, height: 350, borderRadius: 175, backgroundColor: 'rgba(56,189,248,0.15)', filter: 'blur(40px)' },
    bgBlob2: { position: 'absolute', bottom: -50, right: -100, width: 300, height: 300, borderRadius: 150, backgroundColor: 'rgba(99,102,241,0.15)', filter: 'blur(40px)' },

    header: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 16, paddingBottom: 16, paddingTop: 8, borderBottomWidth: 1, borderBottomColor: 'rgba(255,255,255,0.05)' },
    backBtn: { width: 44, height: 44, borderRadius: 22, overflow: 'hidden', alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: 'rgba(255,255,255,0.2)' },
    headerTitle: { fontSize: 20, fontWeight: '900', color: '#F8FAFC' },
    headerSubtitle: { fontSize: 13, fontWeight: '600', color: '#94A3B8', marginTop: 2 },
    
    scrollContent: { padding: 20 },

    sectionTitle: { fontSize: 13, fontWeight: '800', color: '#64748B', letterSpacing: 1, textTransform: 'uppercase', marginBottom: 12, marginTop: 20, marginLeft: 4 },

    settingCard: { marginBottom: 12, borderRadius: 20, overflow: 'hidden', borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)' },
    settingCardInner: { flexDirection: 'row', alignItems: 'center', padding: 16 },
    iconBox: { width: 46, height: 46, borderRadius: 16, alignItems: 'center', justifyContent: 'center', borderWidth: 1 },
    infoWrap: { flex: 1, marginLeft: 16 },
    settingTitle: { fontSize: 16, fontWeight: '800', color: '#F8FAFC', marginBottom: 4 },
    settingSubtitle: { fontSize: 12, fontWeight: '500', color: '#94A3B8' },

    logoutBtn: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', marginTop: 30, paddingVertical: 18, borderRadius: 20, borderWidth: 1, borderColor: 'rgba(239,68,68,0.3)', overflow: 'hidden' },
    logoutTxt: { fontSize: 16, fontWeight: '800', color: '#F87171' }
});
