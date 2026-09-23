import React from 'react';
import { View, Text, StyleSheet, TouchableOpacity, Linking, Platform } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import Icon from '@expo/vector-icons/Ionicons';
import { LinearGradient } from 'expo-linear-gradient';
import SpaceWaves from '../components/SpaceWaves';

export default function SupportScreen({ navigation }) {

    const openWhatsApp = () => {
        const url = 'whatsapp://send?text=Merhaba, ServisPilot hakkında destek almak istiyorum.';
        Linking.canOpenURL(url).then(supported => {
            if (!supported) {
                alert('WhatsApp uygulaması cihazınızda yüklü değil.');
            } else {
                return Linking.openURL(url);
            }
        }).catch(err => console.error('An error occurred', err));
    };

    const openEmail = () => {
        Linking.openURL('mailto:destek@servispilot.com?subject=ServisPilot Teknik Destek Talebi');
    };

    return (
        <View style={s.container}>
            {/* Arka plan animasyonu (Login ile aynı yıldızlı kayan yapı) */}
            <SpaceWaves />
            
            {/* Üstteki kısımların (notch vb.) yazılarla kesişmemesi için ekstra padding eklendi */}
            <SafeAreaView style={{ flex: 1, paddingTop: Platform.OS === 'android' ? 30 : 40 }}>
                
                {/* Header */}
                <View style={s.header}>
                    <TouchableOpacity onPress={() => navigation.goBack()} style={s.backBtn}>
                        <Icon name="arrow-back" size={24} color="#FFF" />
                    </TouchableOpacity>
                    <View style={s.headerTitleWrap}>
                        <Text style={s.headerTitle}>Yazılım Mimarı & Destek</Text>
                    </View>
                </View>

                {/* Main Content */}
                <View style={s.content}>
                    <View style={s.supportCard}>
                        
                        {/* Avatar */}
                        <View style={s.avatarContainer}>
                            <LinearGradient colors={['#3B82F6', '#8B5CF6']} style={s.avatarBg}>
                                <Text style={s.avatarText}>SD</Text>
                            </LinearGradient>
                            <View style={s.statusDotWrap}>
                                <View style={s.statusDot} />
                            </View>
                        </View>

                        {/* Title & Name */}
                        <Text style={s.roleText}>YAZILIM MİMARI</Text>
                        <Text style={s.nameText}>Sabri DOĞRU</Text>

                        {/* Description */}
                        <Text style={s.descText}>
                            Sistemle ilgili teknik sorularınız, yeni özellik talepleriniz veya hata bildirimleriniz için doğrudan benimle iletişime geçebilirsiniz.
                        </Text>

                        {/* Buttons */}
                        <View style={s.buttonRow}>
                            <TouchableOpacity style={s.whatsappBtn} onPress={openWhatsApp} activeOpacity={0.8}>
                                <Icon name="logo-whatsapp" size={20} color="#FFF" style={{ marginRight: 8 }} />
                                <Text style={s.whatsappBtnText}>WhatsApp</Text>
                            </TouchableOpacity>

                            <TouchableOpacity style={s.emailBtn} onPress={openEmail} activeOpacity={0.7}>
                                <Icon name="mail-outline" size={20} color="#E2E8F0" style={{ marginRight: 8 }} />
                                <Text style={s.emailBtnText}>E-posta</Text>
                            </TouchableOpacity>
                        </View>

                    </View>
                </View>

            </SafeAreaView>
        </View>
    );
}

const s = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#020617' }, 
    
    header: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: 20, paddingTop: 20, paddingBottom: 20 },
    backBtn: { width: 44, height: 44, borderRadius: 22, backgroundColor: 'rgba(255,255,255,0.1)', alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: 'rgba(255,255,255,0.05)', marginRight: 15 },
    headerTitleWrap: { flex: 1 },
    headerTitle: { fontSize: 22, fontWeight: '900', color: '#FFFFFF', letterSpacing: -0.5 },
    headerSub: { fontSize: 10, color: '#38BDF8', fontWeight: '800', marginTop: 2, letterSpacing: 0.5 },

    content: { flex: 1, justifyContent: 'center', alignItems: 'center', paddingHorizontal: 20, paddingBottom: 80 },
    
    // Glassmorphism card for dark background
    supportCard: { width: '100%', backgroundColor: 'rgba(15, 23, 42, 0.7)', borderRadius: 32, padding: 30, alignItems: 'center', shadowColor: '#000', shadowOffset: { width: 0, height: 20 }, shadowopacity: 1, shadowRadius: 40, elevation: 15, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)' },
    
    avatarContainer: { position: 'relative', marginBottom: 20 },
    avatarBg: { width: 90, height: 90, borderRadius: 45, alignItems: 'center', justifyContent: 'center', shadowColor: '#8B5CF6', shadowOffset: { width: 0, height: 10 }, shadowOpacity: 0.4, shadowRadius: 20, elevation: 10, borderWidth: 3, borderColor: 'rgba(15, 23, 42, 1)' },
    avatarText: { fontSize: 32, fontWeight: '900', color: '#FFF' },
    statusDotWrap: { position: 'absolute', bottom: 2, right: 2, width: 24, height: 24, borderRadius: 12, backgroundColor: 'rgba(15, 23, 42, 1)', alignItems: 'center', justifyContent: 'center' },
    statusDot: { width: 14, height: 14, borderRadius: 7, backgroundColor: '#10B981', shadowColor: '#10B981', shadowOffset: {width: 0, height: 0}, shadowOpacity: 0.8, shadowRadius: 5 },

    roleText: { fontSize: 11, fontWeight: '800', color: '#8B5CF6', letterSpacing: 1.5, marginBottom: 4 },
    nameText: { fontSize: 28, fontWeight: '900', color: '#FFFFFF', letterSpacing: -1, marginBottom: 20 },
    
    descText: { fontSize: 14, color: '#94A3B8', textAlign: 'center', lineHeight: 22, marginBottom: 30, paddingHorizontal: 10 },

    buttonRow: { flexDirection: 'row', width: '100%', justifyContent: 'space-between' },
    whatsappBtn: { flex: 1, flexDirection: 'row', backgroundColor: '#10B981', paddingVertical: 14, borderRadius: 16, alignItems: 'center', justifyContent: 'center', marginRight: 10, shadowColor: '#10B981', shadowOffset: { width: 0, height: 8 }, shadowopacity: 1, shadowRadius: 12, elevation: 8 },
    whatsappBtnText: { fontSize: 14, fontWeight: '800', color: '#FFF' },
    
    emailBtn: { flex: 1, flexDirection: 'row', backgroundColor: 'rgba(255,255,255,0.05)', borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)', paddingVertical: 14, borderRadius: 16, alignItems: 'center', justifyContent: 'center', marginLeft: 10 },
    emailBtnText: { fontSize: 14, fontWeight: '700', color: '#E2E8F0' },
});
