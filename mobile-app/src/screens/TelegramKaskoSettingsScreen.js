import React, { useContext, useEffect, useState } from 'react';
import { ActivityIndicator, Alert, KeyboardAvoidingView, Platform, ScrollView, StyleSheet, Switch, Text, TextInput, TouchableOpacity, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import Ionicons from '@expo/vector-icons/Ionicons';
import api from '../api/axios';
import { AuthContext } from '../context/AuthContext';
import useStatusBarStyle from '../hooks/useStatusBarStyle';

const DEFAULT_TEMPLATE = `🚨 <b>KASKO BİTİŞ UYARISI</b>

Sayın <b>{{company_name}}</b> yetkilisi,

Aşağıdaki aracın kasko poliçesi {{kasko_status}}

🚘 <b>Plaka:</b> {{plate}}
🏷️ <b>Marka / Model:</b> {{brand_model}}
📅 <b>Model Yılı:</b> {{model_year}}
🔢 <b>Seri No:</b> {{license_serial_no}}
🧾 <b>Şase No:</b> {{chassis_no}}
🛡️ <b>Kasko Bitiş Tarihi:</b> {{kasko_end_date}}

⏳ Poliçe yenileme işlemlerini zamanında yaparak
aracınızın güvence sürekliliğini koruyun.

<i>ServisPilot | Filo Takip Sistemi</i>`;

export default function TelegramKaskoSettingsScreen({ navigation }) {
    useStatusBarStyle('dark-content');
    const { hasPermission } = useContext(AuthContext);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [testing, setTesting] = useState(false);
    const [isEnabled, setIsEnabled] = useState(false);
    const [botToken, setBotToken] = useState('');
    const [hasBotToken, setHasBotToken] = useState(false);
    const [chatId, setChatId] = useState('');
    const [reminderDays, setReminderDays] = useState('10, 15, 20');
    const [messageTemplate, setMessageTemplate] = useState(DEFAULT_TEMPLATE);

    const canManage = hasPermission('telegram.manage');

    useEffect(() => {
        loadSettings();
    }, []);

    const loadSettings = async () => {
        try {
            const response = await api.get('/v1/telegram-kasko-settings');
            const settings = response.data?.data?.settings;
            if (settings) {
                setIsEnabled(!!settings.is_enabled);
                setHasBotToken(!!settings.has_bot_token);
                setChatId(settings.chat_id || '');
                setReminderDays((settings.reminder_days || [10, 15, 20]).join(', '));
                setMessageTemplate(settings.message_template || DEFAULT_TEMPLATE);
            }
        } catch (error) {
            Alert.alert('Hata', error.response?.data?.message || 'Telegram ayarları yüklenemedi.');
        } finally {
            setLoading(false);
        }
    };

    const parseReminderDays = () => {
        const days = [...new Set(
            reminderDays
                .split(/[\s,;]+/)
                .filter(Boolean)
                .map(value => Number(value))
                .filter(value => Number.isInteger(value) && value >= -365 && value <= 365)
        )].sort((a, b) => a - b);

        if (!days.length) {
            Alert.alert('Uyarı günleri', '-365 ile 365 arasında en az bir gün girin. Eksi değer, bitişten sonraki günü ifade eder.');
            return null;
        }

        return days;
    };

    const saveSettings = async () => {
        if (!canManage) {
            Alert.alert('Yetki Hatası', 'Telegram ayarlarını yönetme yetkiniz yok.');
            return;
        }

        const days = parseReminderDays();
        if (!days) return;
        if (!messageTemplate.trim()) {
            Alert.alert('Uyarı Metni', 'Uyarı metni boş bırakılamaz.');
            return;
        }

        setSaving(true);
        try {
            const payload = {
                is_enabled: isEnabled,
                chat_id: chatId.trim(),
                reminder_days: days,
                message_template: messageTemplate,
            };
            if (botToken.trim()) payload.bot_token = botToken.trim();

            const response = await api.put('/v1/telegram-kasko-settings', payload);
            const settings = response.data?.data?.settings;
            setHasBotToken(!!settings?.has_bot_token);
            setBotToken('');
            setReminderDays((settings?.reminder_days || days).join(', '));
            Alert.alert('Başarılı', 'Telegram kasko bildirim ayarları kaydedildi.');
        } catch (error) {
            const errors = error.response?.data?.errors;
            const firstError = errors ? Object.values(errors).flat()[0] : null;
            Alert.alert('Kaydedilemedi', firstError || error.response?.data?.message || 'Ayarlar kaydedilemedi.');
        } finally {
            setSaving(false);
        }
    };

    const sendTest = async () => {
        setTesting(true);
        try {
            const response = await api.post('/v1/telegram-kasko-settings/test');
            Alert.alert('Test Mesajı Gönderildi', response.data?.message || 'Telegram mesajı gönderildi.');
        } catch (error) {
            Alert.alert('Test Gönderilemedi', error.response?.data?.message || 'Önce ayarları kaydedin ve bot bağlantısını kontrol edin.');
        } finally {
            setTesting(false);
        }
    };

    if (loading) {
        return <SafeAreaView style={styles.loading}><ActivityIndicator size="large" color="#0284C7" /></SafeAreaView>;
    }

    return (
        <SafeAreaView style={styles.container} edges={['top', 'bottom', 'left', 'right']}>
            <View style={styles.header}>
                <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backButton}><Ionicons name="arrow-back" size={23} color="#0F172A" /></TouchableOpacity>
                <View style={styles.headerCopy}><Text style={styles.headerTitle}>Telegram</Text><Text style={styles.headerSubtitle}>KASKO UYARILARI</Text></View>
                <View style={styles.telegramBadge}><Ionicons name="paper-plane" size={19} color="#fff" /></View>
            </View>

            <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
                <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
                    <View style={styles.hero}>
                        <View style={styles.heroIcon}><Text style={{ fontSize: 30 }}>✈️</Text></View>
                        <View style={{ flex: 1 }}>
                            <Text style={styles.heroStatus}>{isEnabled ? '● AKTİF OTOMASYON' : '● OTOMASYON BEKLEMEDE'}</Text>
                            <Text style={styles.heroTitle}>Kasko Asistanı</Text>
                            <Text style={styles.heroText}>Seçtiğiniz günlerde araç bilgileriyle premium Telegram uyarısı gönderir.</Text>
                        </View>
                    </View>

                    <View style={styles.card}>
                        <View style={styles.sectionTitleRow}><Text style={styles.sectionEmoji}>🤖</Text><View><Text style={styles.sectionTitle}>Bot bağlantısı</Text><Text style={styles.sectionDescription}>Bot anahtarınızı ve sohbet kimliğinizi girin.</Text></View></View>
                        <View style={styles.switchRow}>
                            <View style={{ flex: 1 }}><Text style={styles.switchTitle}>Otomatik uyarıları etkinleştir</Text><Text style={styles.switchDescription}>Her gün 09:00'da kontrol edilir.</Text></View>
                            <Switch value={isEnabled} onValueChange={setIsEnabled} disabled={!canManage} trackColor={{ false: '#CBD5E1', true: '#38BDF8' }} thumbColor="#fff" />
                        </View>
                        <Text style={styles.inputLabel}>BOT ANAHTARI {hasBotToken ? '• KAYITLI' : ''}</Text>
                        <TextInput value={botToken} onChangeText={setBotToken} editable={canManage} secureTextEntry placeholder={hasBotToken ? 'Değiştirmek için yeni anahtarı yazın' : '123456:ABC-DEF...'} placeholderTextColor="#94A3B8" style={styles.input} autoCapitalize="none" autoCorrect={false} />
                        <Text style={styles.inputLabel}>SOHBET / GRUP KİMLİĞİ</Text>
                        <TextInput value={chatId} onChangeText={setChatId} editable={canManage} placeholder="Örn: -1001234567890" placeholderTextColor="#94A3B8" style={styles.input} autoCapitalize="none" autoCorrect={false} />
                    </View>

                    <View style={styles.card}>
                        <View style={styles.sectionTitleRow}><Text style={styles.sectionEmoji}>⏰</Text><View><Text style={styles.sectionTitle}>Uyarı günleri</Text><Text style={styles.sectionDescription}>Virgülle istediğiniz kadar gün ekleyin.</Text></View></View>
                        <Text style={styles.inputLabel}>KALAN GÜNLER</Text>
                        <TextInput value={reminderDays} onChangeText={setReminderDays} editable={canManage} keyboardType="numbers-and-punctuation" placeholder="10, 15, 20" placeholderTextColor="#94A3B8" style={styles.input} />
                        <Text style={styles.helpText}>Örn: 30, 20, 15, 10, 0, -1, -7 • Eksi değer bitişten sonradır.</Text>
                    </View>

                    <View style={styles.card}>
                        <View style={styles.sectionTitleRow}><Text style={styles.sectionEmoji}>✨</Text><View><Text style={styles.sectionTitle}>Premium uyarı metni</Text><Text style={styles.sectionDescription}>Alanlar mesaj gönderilirken otomatik doldurulur.</Text></View></View>
                        <TextInput value={messageTemplate} onChangeText={setMessageTemplate} editable={canManage} multiline textAlignVertical="top" style={[styles.input, styles.templateInput]} />
                        <Text style={styles.helpText}>Alanlar: {'{{kasko_status}}'}, {'{{plate}}'}, {'{{brand_model}}'}, {'{{model_year}}'}, {'{{license_serial_no}}'}, {'{{chassis_no}}'}, {'{{kasko_end_date}}'}</Text>
                    </View>

                    {!canManage && <View style={styles.permissionNotice}><Ionicons name="lock-closed-outline" size={18} color="#B45309" /><Text style={styles.permissionText}>Bu ayarları değiştirme yetkiniz yok.</Text></View>}

                    <TouchableOpacity style={[styles.saveButton, (!canManage || saving) && styles.disabledButton]} disabled={!canManage || saving} onPress={saveSettings}>
                        {saving ? <ActivityIndicator color="#fff" /> : <><Ionicons name="save-outline" size={20} color="#fff" /><Text style={styles.saveText}>Ayarları Kaydet</Text></>}
                    </TouchableOpacity>
                    <TouchableOpacity style={[styles.testButton, testing && styles.disabledButton]} disabled={testing} onPress={sendTest}>
                        {testing ? <ActivityIndicator color="#0369A1" /> : <><Ionicons name="paper-plane-outline" size={20} color="#0369A1" /><Text style={styles.testText}>Test Mesajı Gönder</Text></>}
                    </TouchableOpacity>
                    <Text style={styles.footer}>Filo Merkez | Filo Takip Sistemleri</Text>
                </ScrollView>
            </KeyboardAvoidingView>
        </SafeAreaView>
    );
}

const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#F8FAFC' },
    loading: { flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: '#F8FAFC' },
    header: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 18, paddingVertical: 14, backgroundColor: '#fff', borderBottomWidth: 1, borderBottomColor: '#E2E8F0' },
    backButton: { width: 42, height: 42, borderRadius: 14, alignItems: 'center', justifyContent: 'center', backgroundColor: '#F1F5F9' },
    headerCopy: { flex: 1 }, headerTitle: { fontSize: 19, fontWeight: '900', color: '#0F172A' }, headerSubtitle: { marginTop: 2, fontSize: 9, fontWeight: '900', letterSpacing: 1.4, color: '#64748B' },
    telegramBadge: { width: 40, height: 40, borderRadius: 14, alignItems: 'center', justifyContent: 'center', backgroundColor: '#0284C7' },
    content: { padding: 18, paddingBottom: 42 },
    hero: { flexDirection: 'row', gap: 14, alignItems: 'center', borderRadius: 24, backgroundColor: '#0F172A', padding: 18, marginBottom: 18 },
    heroIcon: { width: 54, height: 54, borderRadius: 18, alignItems: 'center', justifyContent: 'center', backgroundColor: 'rgba(56,189,248,0.18)' },
    heroStatus: { fontSize: 9, fontWeight: '900', letterSpacing: 1.1, color: '#7DD3FC' }, heroTitle: { marginTop: 3, fontSize: 19, fontWeight: '900', color: '#fff' }, heroText: { marginTop: 3, fontSize: 12, lineHeight: 17, fontWeight: '500', color: '#CBD5E1' },
    card: { backgroundColor: '#fff', borderRadius: 22, padding: 17, marginBottom: 14, borderWidth: 1, borderColor: '#E2E8F0' },
    sectionTitleRow: { flexDirection: 'row', alignItems: 'center', gap: 10, marginBottom: 16 }, sectionEmoji: { fontSize: 22 }, sectionTitle: { fontSize: 16, fontWeight: '900', color: '#0F172A' }, sectionDescription: { marginTop: 2, fontSize: 12, fontWeight: '500', color: '#64748B' },
    switchRow: { flexDirection: 'row', alignItems: 'center', gap: 10, padding: 13, borderRadius: 15, backgroundColor: '#F8FAFC', marginBottom: 15 }, switchTitle: { fontSize: 13, fontWeight: '800', color: '#1E293B' }, switchDescription: { marginTop: 2, fontSize: 11, fontWeight: '500', color: '#64748B' },
    inputLabel: { marginBottom: 7, marginTop: 10, fontSize: 10, fontWeight: '900', letterSpacing: 0.8, color: '#475569' }, input: { minHeight: 46, borderWidth: 1, borderColor: '#CBD5E1', borderRadius: 13, paddingHorizontal: 13, paddingVertical: 11, fontSize: 13, fontWeight: '600', color: '#0F172A', backgroundColor: '#fff' },
    templateInput: { height: 315, fontFamily: Platform.select({ ios: 'Menlo', android: 'monospace' }), lineHeight: 19, fontSize: 12 }, helpText: { marginTop: 9, fontSize: 11, lineHeight: 16, fontWeight: '500', color: '#64748B' },
    permissionNotice: { flexDirection: 'row', alignItems: 'center', gap: 8, marginBottom: 14, padding: 13, borderRadius: 14, backgroundColor: '#FEF3C7' }, permissionText: { flex: 1, fontSize: 12, fontWeight: '700', color: '#92400E' },
    saveButton: { minHeight: 52, borderRadius: 16, backgroundColor: '#0284C7', flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8, marginTop: 5 }, saveText: { fontSize: 15, fontWeight: '900', color: '#fff' },
    testButton: { minHeight: 52, borderRadius: 16, borderWidth: 1, borderColor: '#7DD3FC', backgroundColor: '#F0F9FF', flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8, marginTop: 11 }, testText: { fontSize: 14, fontWeight: '900', color: '#0369A1' }, disabledButton: { opacity: 0.55 }, footer: { textAlign: 'center', marginTop: 22, fontSize: 11, fontWeight: '800', color: '#94A3B8' },
});
